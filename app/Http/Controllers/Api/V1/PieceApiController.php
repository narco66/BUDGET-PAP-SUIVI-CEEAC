<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\PieceJustificative;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pièces du dossier de dépense (EB §2.4 : PJ sans forcer le fournisseur). */
class PieceApiController extends Controller
{
    private const TYPES = [
        'besoin' => ExpressionBesoin::class,
        'engagement' => Engagement::class,
        'liquidation' => Liquidation::class,
        'ordonnancement' => Ordonnancement::class,
        'paiement' => Paiement::class,
    ];

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $objet = $this->resoudre($type, $id);
        $this->authorize('view', $objet);

        return response()->json([
            'data' => $objet->pieces()->get()->map(fn (PieceJustificative $p) => $this->serialiser($p))->values(),
            'peut_ajouter' => $this->peutAjouter($request, $objet),
        ]);
    }

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $objet = $this->resoudre($type, $id);
        abort_unless($this->peutAjouter($request, $objet), 403);

        $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'categorie' => ['nullable', 'string', 'max:50'],
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('ged/'.now()->format('Y/m'), 'local');

        $piece = $objet->pieces()->create([
            'nom' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'type_mime' => $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
            'categorie' => $request->input('categorie', 'justificatif'),
            'televerse_par' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Pièce justificative ajoutée.',
            'code' => 'piece_ajoutee',
            'piece' => $this->serialiser($piece),
        ], 201);
    }

    public function fichier(Request $request, PieceJustificative $piece): StreamedResponse
    {
        $piece->loadMissing('attachable');
        abort_unless($piece->attachable !== null, 404);
        $this->authorize('view', $piece->attachable);
        abort_unless(Storage::disk('local')->exists($piece->chemin), 404);

        return Storage::disk('local')->download($piece->chemin, $piece->nom);
    }

    public function destroy(Request $request, PieceJustificative $piece): JsonResponse
    {
        $piece->loadMissing('attachable');
        abort_unless($piece->attachable !== null, 404);
        abort_unless($this->peutAjouter($request, $piece->attachable), 403);

        Storage::disk('local')->delete($piece->chemin);
        $piece->delete();

        return response()->json([
            'message' => 'Pièce supprimée.',
            'code' => 'piece_supprimee',
        ]);
    }

    private function peutAjouter(Request $request, Model $objet): bool
    {
        $user = $request->user();
        if ($user === null || ! $user->can('view', $objet)) {
            return false;
        }

        return $user->can('update', $objet) || $user->can('agir', $objet);
    }

    private function resoudre(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function serialiser(PieceJustificative $piece): array
    {
        return [
            'id' => $piece->id,
            'nom' => $piece->nom,
            'categorie' => $piece->categorie,
            'type_mime' => $piece->type_mime,
            'taille' => $piece->taille,
            'created_at' => $piece->created_at?->toIso8601String(),
        ];
    }
}
