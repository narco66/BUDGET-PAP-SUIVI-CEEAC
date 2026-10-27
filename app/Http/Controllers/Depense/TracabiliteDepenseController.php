<?php

namespace App\Http\Controllers\Depense;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\WorkflowTache;
use App\Services\AccessScopeService;
use App\Services\Depense\TracabiliteDepenseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Chaîne de dépense → Traçabilité et suivi des dossiers : recherche universelle (EB, ENG, LIQ, ORD, PAY,
 * bénéficiaire, référence bancaire, montant) et fiche de traçabilité intégrale. Lecture seule, cloisonnée.
 */
class TracabiliteDepenseController extends Controller
{
    private const PREFIXES = ['EB' => 'besoin', 'ENG' => 'engagement', 'LIQ' => 'liquidation', 'ORD' => 'ordonnancement', 'PAY' => 'paiement', 'OP' => 'paiement'];

    public function __construct(
        private readonly TracabiliteDepenseService $tracabilite,
        private readonly AccessScopeService $scope,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $q = trim((string) $request->query('q', ''));
        $resultats = $q !== '' ? $this->rechercher($q, $request->user()) : collect();
        if ($resultats->count() === 1 && $request->boolean('aller', true)) {
            $r = $resultats->first();

            return redirect()->route('depense.tracabilite.show', [$r['type'], $r['objet']->getKey()]);
        }

        return view('depense.tracabilite.index', [
            'q' => $q,
            'resultats' => $resultats,
            'indicateurs' => $this->indicateurs($request->user()),
        ]);
    }

    public function show(Request $request, string $type, int $id): View
    {
        $classe = TracabiliteDepenseService::TYPES[$type] ?? abort(404);
        /** @var Model $objet */
        $objet = $classe::query()->findOrFail($id);
        $this->authorize('view', $objet);

        return view('depense.tracabilite.show', [
            'dossier' => $this->tracabilite->dossier($objet),
            'onglet' => $request->query('onglet', 'synthese'),
        ]);
    }

    /** @return Collection<int, array{type: string, objet: Model, motif: string}> */
    private function rechercher(string $q, $user): Collection
    {
        $resultats = collect();
        $ajouter = function (string $type, Builder $query, string $motif) use (&$resultats, $user) {
            $query = $type === 'besoin'
                ? $this->scope->filtrerStructuresMetier($query, $user)
                : $this->scope->filtrerDossiersDepense($query, $user);
            foreach ($query->limit(25)->get() as $o) {
                if ($user->can('view', $o) && $resultats->doesntContain(fn ($r) => $r['objet']->is($o))) {
                    $resultats->push(['type' => $type, 'objet' => $o, 'motif' => $motif]);
                }
            }
        };
        $like = '%'.mb_strtoupper($q).'%';

        // Référence de dossier : le préfixe oriente la recherche, sinon tous les types sont interrogés.
        $prefixe = strtoupper((string) strtok($q, '-'));
        $types = isset(self::PREFIXES[$prefixe]) ? [self::PREFIXES[$prefixe]] : array_keys(TracabiliteDepenseService::TYPES);
        foreach ($types as $type) {
            $classe = TracabiliteDepenseService::TYPES[$type];
            $ajouter($type, $classe::query()->whereRaw('UPPER(numero) LIKE ?', [$like]), 'Référence du dossier');
        }
        if ($resultats->isNotEmpty()) {
            return $resultats;
        }

        $ajouter('besoin', ExpressionBesoin::query()->whereRaw('UPPER(objet) LIKE ?', [$like]), 'Objet du besoin');
        $ajouter('engagement', Engagement::query()->where(fn ($w) => $w->whereRaw('UPPER(beneficiaire) LIKE ?', [$like])->orWhereRaw('UPPER(objet) LIKE ?', [$like])), 'Bénéficiaire ou objet');
        $ajouter('paiement', Paiement::query()->whereRaw('UPPER(COALESCE(reference_bancaire, \'\')) LIKE ?', [$like]), 'Référence bancaire');
        $montant = (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', $q));
        if ($montant > 0 && preg_match('/^[\d\s.,]+$/', $q)) {
            $ajouter('engagement', Engagement::query()->where('montant_total', $montant), 'Montant engagé');
            $ajouter('liquidation', Liquidation::query()->where('montant', $montant), 'Montant liquidé');
            $ajouter('ordonnancement', Ordonnancement::query()->where('montant', $montant), 'Montant ordonnancé');
            $ajouter('paiement', Paiement::query()->where('montant', $montant), 'Montant payé');
        }

        return $resultats;
    }

    /** @return array<string, int> indicateurs de suivi sur les tâches ouvertes de la chaîne (périmètre global) */
    private function indicateurs($user): array
    {
        $types = array_map(fn ($c) => (new $c)->getMorphClass(), TracabiliteDepenseService::TYPES);
        $ouvertes = WorkflowTache::query()->ouvertes()->whereIn('objet_type', $types);

        return [
            'en_attente' => (clone $ouvertes)->count(),
            'en_retard' => (clone $ouvertes)->whereNotNull('echeance_le')->where('echeance_le', '<', now())->count(),
            'non_prises' => (clone $ouvertes)->whereNull('assigne_a')->count(),
            'affectation_defaut' => (clone $ouvertes)->where('affectation_defaut', true)->count(),
            'a_corriger' => ExpressionBesoin::query()->where('statut', 'a_corriger')->count()
                + Engagement::query()->where('statut', 'a_corriger')->count()
                + Liquidation::query()->where('statut', 'a_corriger')->count()
                + Ordonnancement::query()->where('statut', 'a_corriger')->count()
                + Paiement::query()->where('statut', 'a_corriger')->count(),
        ];
    }
}
