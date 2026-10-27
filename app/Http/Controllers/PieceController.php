<?php

namespace App\Http\Controllers;

use App\Models\AppelFonds;
use App\Models\Encaissement;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\PieceJustificative;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceController extends Controller
{
    /** Types d'objets porteurs de pièces (alias public => modèle). */
    private const TYPES = [
        'besoin' => ExpressionBesoin::class,
        'engagement' => Engagement::class,
        'liquidation' => Liquidation::class,
        'ordonnancement' => Ordonnancement::class,
        'paiement' => Paiement::class,
        'encaissement' => Encaissement::class,
        'appel' => AppelFonds::class,
    ];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $objet = $this->resoudre($type, $id);
        abort_unless(self::peutAjouter($request->user(), $objet), 403);

        $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'categorie' => ['nullable', 'string', 'max:50'],
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('ged/'.now()->format('Y/m'), 'local');

        $objet->pieces()->create([
            'nom' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'type_mime' => $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
            'categorie' => $request->input('categorie', 'justificatif'),
            'televerse_par' => $request->user()->id,
        ]);

        return back()->with('succes', 'Pièce justificative ajoutée.');
    }

    public function download(Request $request, PieceJustificative $piece): StreamedResponse
    {
        abort_unless(self::peutVoir($request->user(), $piece->attachable), 403);
        abort_unless(Storage::disk('local')->exists($piece->chemin), 404);

        return Storage::disk('local')->download($piece->chemin, $piece->nom);
    }

    public function destroy(Request $request, PieceJustificative $piece): RedirectResponse
    {
        abort_unless(self::peutSupprimer($request->user(), $piece), 403);

        // Une pièce intégrée à la GED conserve son fichier : le document GED est archivé, jamais effacé.
        if (! $piece->ged_document_id) {
            Storage::disk('local')->delete($piece->chemin);
        }
        $piece->delete();

        return back()->with('succes', $piece->ged_document_id ? 'Pièce retirée du dossier (conservée et archivée dans la GED).' : 'Pièce supprimée.');
    }

    /**
     * Joindre une pièce : droit GED de création, ou droit de modifier le dossier lui-même (le rédacteur d'une
     * expression de besoin joint ses justificatifs sans permission GED générale).
     */
    public static function peutAjouter(User $user, ?Model $objet): bool
    {
        if ($user->can('ged.creer') || ($objet && self::peutModifierPieces($user, $objet))) {
            return true;
        }
        // L'acteur de l'étape en cours joint les pièces de son acte (ex. PV et facture lors de la certification du service fait).
        $instance = $objet && method_exists($objet, 'workflowActif') ? $objet->workflowActif : null;

        return $instance && $instance->statut === 'en_cours' && app(\App\Services\WorkflowService::class)->peutAgir($instance, $user);
    }

    /** Télécharger : droit GED de consultation, ou droit de consulter le dossier. */
    public static function peutVoir(User $user, ?Model $objet): bool
    {
        return $user->can('ged.voir') || ($objet && Gate::forUser($user)->allows('view', $objet));
    }

    /** Supprimer : droit GED de suppression, ou auteur de la pièce tant que le dossier reste modifiable. */
    public static function peutSupprimer(User $user, PieceJustificative $piece): bool
    {
        return $user->can('ged.supprimer')
            || ((int) $piece->televerse_par === $user->id && $piece->attachable && self::peutModifierPieces($user, $piece->attachable));
    }

    /** Droit de gérer les pièces du dossier : le modifier, ou la capacité dédiée de sa politique (ex. demandeur d'une EB). */
    private static function peutModifierPieces(User $user, Model $objet): bool
    {
        $gate = Gate::forUser($user);
        $politique = Gate::getPolicyFor($objet);

        return $gate->allows('update', $objet)
            || ($politique && method_exists($politique, 'joindrePieces') && $gate->allows('joindrePieces', $objet));
    }

    /**
     * Dossiers en amont dans la chaîne de la dépense (EB → ENG → LIQ → ORD → PAY), dont les pièces restent
     * consultables à chaque étape suivante.
     *
     * @return list<array{0: string, 1: Model}> [libellé de l'étape, dossier]
     */
    public static function dossiersAmont(Model $objet): array
    {
        $amont = [];
        $courant = $objet;
        for ($i = 0; $i < 5 && $courant; $i++) {
            $parent = match (true) {
                $courant instanceof Paiement => $courant->ordonnancement,
                $courant instanceof Ordonnancement => $courant->liquidation,
                $courant instanceof Liquidation => $courant->engagement,
                $courant instanceof Engagement => $courant->expressionBesoin,
                default => null,
            };
            if ($parent) {
                $etape = match (true) {
                    $parent instanceof Ordonnancement => 'Ordonnancement',
                    $parent instanceof Liquidation => 'Liquidation',
                    $parent instanceof Engagement => 'Engagement',
                    default => 'Expression de besoin',
                };
                $amont[] = [$etape, $parent];
            }
            $courant = $parent;
        }

        return $amont;
    }

    private function resoudre(string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::findOrFail($id);
    }
}
