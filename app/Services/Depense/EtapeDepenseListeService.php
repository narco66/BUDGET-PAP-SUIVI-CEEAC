<?php

namespace App\Services\Depense;

use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\AccessScopeService;
use App\Services\Workflow\TacheQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liste des étapes aval (LIQ / ORD / PAY) : filtres, indicateurs par statut, origine du dossier, suite
 * financière (ordonnancé, payé, reste) et action attendue — sans requête par ligne (chargement groupé).
 */
class EtapeDepenseListeService
{
    public const TRIS = ['recent' => 'Plus récents', 'ancien' => 'Plus anciens', 'montant_desc' => 'Montant décroissant', 'montant_asc' => 'Montant croissant', 'numero' => 'Numéro'];

    private const EXCLUS = ['rejete', 'annule'];

    public function __construct(
        private readonly AccessScopeService $scope,
        private readonly TacheQueryService $taches,
    ) {}

    /** @return array<string, mixed> */
    public function lister(string $type, array $config, Request $request, User $user): array
    {
        $filtres = $this->filtres($request);
        $base = $this->scope->filtrerDossiersDepense($config['model']::query(), $user);
        $this->appliquerFiltres($base, $type, $config, $filtres, sansStatut: true);

        // Indicateurs par statut sur le périmètre filtré (hors filtre de statut, pour rester cliquables).
        $colonneMontant = $config['colonne_montant'] ?? 'montant';
        $parStatut = (clone $base)->toBase()->select('statut', DB::raw('count(*) as n'), DB::raw("coalesce(sum({$colonneMontant}),0) as total"))
            ->groupBy('statut')->get()->keyBy('statut');

        $query = clone $base;
        if ($filtres['statut'] !== '') {
            $query->where('statut', $filtres['statut']);
        }
        $this->charger($query, $type);
        match ($filtres['tri']) {
            'ancien' => $query->oldest()->orderBy('id'),
            'montant_desc' => $query->orderByDesc($colonneMontant)->orderByDesc('id'),
            'montant_asc' => $query->orderBy($colonneMontant)->orderByDesc('id'),
            'numero' => $query->orderBy('numero'),
            default => $query->latest()->orderByDesc('id'),
        };
        $ouvertes = $this->tachesOuvertes($config['model']);
        /** @var LengthAwarePaginator $items */
        $items = $query->paginate($filtres['par_page'])->withQueryString();

        return [
            'items' => $items,
            'filtres' => $filtres,
            'indicateurs' => $parStatut,
            'total' => ['n' => (int) $parStatut->sum('n'), 'montant' => (float) $parStatut->sum('total')],
            'attentes' => $this->attentes($items->getCollection(), $ouvertes, $user),
            'enAttenteMoi' => $ouvertes->filter(fn (WorkflowTache $t) => $this->taches->peutTraiter($t, $user))->count(),
        ];
    }

    /** @return array{q: string, statut: string, du: string, au: string, ordonnateur: string, type_engagement: string, tri: string, par_page: int} */
    private function filtres(Request $request): array
    {
        $parPage = (int) $request->query('par_page', 20);

        return [
            'q' => trim((string) $request->query('q', '')),
            'statut' => (string) $request->query('statut', ''),
            'du' => (string) $request->query('du', ''),
            'au' => (string) $request->query('au', ''),
            'ordonnateur' => (string) $request->query('ordonnateur', ''),
            'type_engagement' => (string) $request->query('type_engagement', ''),
            'tri' => array_key_exists((string) $request->query('tri'), self::TRIS) ? (string) $request->query('tri') : 'recent',
            'par_page' => in_array($parPage, [20, 50, 100], true) ? $parPage : 20,
        ];
    }

    private function appliquerFiltres(Builder $q, string $type, array $config, array $f, bool $sansStatut = false): void
    {
        if ($f['q'] !== '') {
            $like = '%'.mb_strtoupper($f['q']).'%';
            $chemin = match ($type) {
                'engagement' => null,
                'liquidation' => 'engagement',
                'ordonnancement' => 'liquidation.engagement',
                default => 'ordonnancement.liquidation.engagement',
            };
            // Champs de l'engagement (le dossier lui-même pour un ENG, l'engagement d'origine sinon).
            $surEngagement = fn (Builder $e) => $e->whereRaw('UPPER(numero) LIKE ?', [$like])
                ->orWhereRaw('UPPER(COALESCE(beneficiaire, \'\')) LIKE ?', [$like])
                ->orWhereRaw('UPPER(COALESCE(objet, \'\')) LIKE ?', [$like])
                ->orWhereHas('expressionBesoin', fn (Builder $b) => $b->whereRaw('UPPER(numero) LIKE ?', [$like]));
            $q->where(function (Builder $w) use ($like, $chemin, $type, $surEngagement) {
                $chemin === null
                    ? $w->where($surEngagement)
                    : $w->whereRaw('UPPER(numero) LIKE ?', [$like])->orWhereHas($chemin, $surEngagement);
                if ($type === 'paiement') {
                    $w->orWhereRaw('UPPER(COALESCE(reference_bancaire, \'\')) LIKE ?', [$like]);
                }
            });
        }
        $colonneDate = $config['date_cle'];
        if ($f['du'] !== '') {
            $q->whereRaw("COALESCE({$colonneDate}, CAST(created_at AS DATE)) >= ?", [$f['du']]);
        }
        if ($f['au'] !== '') {
            $q->whereRaw("COALESCE({$colonneDate}, CAST(created_at AS DATE)) <= ?", [$f['au']]);
        }
        if ($type === 'engagement' && $f['type_engagement'] !== '') {
            $q->where('type', $f['type_engagement']);
        }
        if ($type === 'ordonnancement' && $f['ordonnateur'] !== '') {
            $q->where('ordonnateur_role', $f['ordonnateur']);
        }
        if (! $sansStatut && $f['statut'] !== '') {
            $q->where('statut', $f['statut']);
        }
    }

    /** Relations et agrégats nécessaires à l'affichage, en requêtes groupées. */
    private function charger(Builder $q, string $type): void
    {
        $nonExclu = fn ($r) => $r->whereNotIn('statut', self::EXCLUS);
        match ($type) {
            'engagement' => $q->with(['createur:id,name', 'expressionBesoin:id,numero'])
                ->withSum(['liquidations as suite_montant' => $nonExclu], 'montant'),
            'liquidation' => $q->with(['createur:id,name', 'engagement:id,numero,objet,beneficiaire,expression_besoin_id,montant_total', 'engagement.expressionBesoin:id,numero'])
                ->withSum(['ordonnancements as suite_montant' => $nonExclu], 'montant'),
            'ordonnancement' => $q->with(['createur:id,name', 'liquidation:id,numero,engagement_id', 'liquidation.engagement:id,numero,objet,beneficiaire,expression_besoin_id', 'liquidation.engagement.expressionBesoin:id,numero'])
                ->withSum(['paiements as suite_montant' => fn ($r) => $r->whereNotIn('statut', self::EXCLUS)
                    ->where(fn ($e) => $e->whereNull('etat_execution')->orWhere('etat_execution', '!=', 'instrument_rejete'))], 'montant')
                ->withCount('paiements'),
            default => $q->with(['createur:id,name', 'ordonnancement:id,numero,liquidation_id', 'ordonnancement.liquidation:id,engagement_id', 'ordonnancement.liquidation.engagement:id,numero,objet,beneficiaire,expression_besoin_id', 'ordonnancement.liquidation.engagement.expressionBesoin:id,numero']),
        };
    }

    /** Tâches ouvertes de ce type de dossier, chargées une fois avec ce que requiert le calcul d'éligibilité. */
    private function tachesOuvertes(string $modele): Collection
    {
        return WorkflowTache::query()->where('objet_type', (new $modele)->getMorphClass())->ouvertes()
            ->with(['assigne:id,name', 'etape', 'instance', 'objet', 'structure'])->orderByDesc('id')->get();
    }

    /**
     * Action attendue par dossier de la page : tâche ouverte (étape de circuit ou préparation) et acteur.
     *
     * @return array<int, array{libelle: string, acteur: string, en_retard: bool, pour_moi: bool, echeance: mixed}>
     */
    private function attentes(Collection $items, Collection $ouvertes, User $user): array
    {
        $taches = $ouvertes->whereIn('objet_id', $items->modelKeys())->unique('objet_id');
        $roles = $taches->isEmpty() ? collect() : DB::table('roles')->pluck('libelle', 'name');

        return $taches->mapWithKeys(fn (WorkflowTache $t) => [$t->objet_id => [
            'libelle' => $t->libelle_etape ?: ($roles[$t->role] ?? $t->role),
            'acteur' => $t->assigne?->name ?? ($roles[$t->role] ?? str_replace('_', ' ', (string) $t->role)),
            'en_retard' => $t->enRetard(),
            'echeance' => $t->echeance_le,
            'pour_moi' => $this->taches->peutTraiter($t, $user),
        ]])->all();
    }

    /** @return array<string, string> */
    public static function ordonnateurs(): array
    {
        return ['secretaire_general' => 'Secrétaire général', 'president' => 'Président de la Commission'];
    }

    public static function libelleSuite(string $type): ?string
    {
        return ['engagement' => 'Liquidé', 'liquidation' => 'Ordonnancé', 'ordonnancement' => 'Payé'][$type] ?? null;
    }
}
