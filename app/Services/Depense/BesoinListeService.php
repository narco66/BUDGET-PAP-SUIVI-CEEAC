<?php

namespace App\Services\Depense;

use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\AccessScopeService;
use App\Services\Workflow\TacheQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liste des expressions de besoin : filtres, indicateurs par statut, avancement dans la chaîne
 * (ENG → LIQ → ORD → PAY) et action attendue, en requêtes groupées (aucune requête par ligne).
 */
class BesoinListeService
{
    public const TRIS = ['recent' => 'Plus récents', 'ancien' => 'Plus anciens', 'montant_desc' => 'Montant décroissant', 'montant_asc' => 'Montant croissant', 'priorite' => 'Priorité'];

    public const STATUTS = [
        'brouillon' => ['Brouillon', 'secondary', 'pencil'],
        'soumis' => ['En validation', 'warning', 'hourglass-split'],
        'a_corriger' => ['À corriger', 'info', 'arrow-counterclockwise'],
        'valide' => ['Validé', 'success', 'check-circle'],
        'converti' => ['Converti', 'primary', 'arrow-right-circle'],
        'rejete' => ['Rejeté', 'danger', 'x-circle'],
    ];

    private const EXCLUS = ['rejete', 'annule'];

    public function __construct(
        private readonly AccessScopeService $scope,
        private readonly TacheQueryService $taches,
    ) {}

    /** @return array<string, mixed> */
    public function lister(Request $request, User $user): array
    {
        $f = $this->filtres($request, $user);
        $base = $this->scope->filtrerStructuresMetier(ExpressionBesoin::query(), $user);
        $this->appliquerFiltres($base, $f, $user);

        $parStatut = (clone $base)->toBase()->select('statut', DB::raw('count(*) as n'), DB::raw('coalesce(sum(montant_estime),0) as total'))
            ->groupBy('statut')->get()->keyBy('statut');

        $query = (clone $base)->when($f['statut'] !== '', fn ($q) => $q->where('statut', $f['statut']))
            ->with(['structure:id,code,libelle', 'demandeur:id,name', 'tache:id,code,intitule', 'activite:id,code,intitule',
                'engagement:id,expression_besoin_id,numero,statut,montant_total']);
        match ($f['tri']) {
            'ancien' => $query->oldest()->orderBy('id'),
            'montant_desc' => $query->orderByDesc('montant_estime')->orderByDesc('id'),
            'montant_asc' => $query->orderBy('montant_estime')->orderByDesc('id'),
            'priorite' => $query->orderByRaw("CASE priorite WHEN 'haute' THEN 1 WHEN 'moyenne' THEN 2 ELSE 3 END")->latest(),
            default => $query->latest()->orderByDesc('id'),
        };
        $besoins = $query->paginate($f['par_page'])->withQueryString();

        $ouvertes = WorkflowTache::query()->where('objet_type', (new ExpressionBesoin)->getMorphClass())->ouvertes()
            ->with(['assigne:id,name', 'etape', 'instance', 'objet', 'structure'])->orderByDesc('id')->get();

        return [
            'besoins' => $besoins,
            'filtres' => $f,
            'indicateurs' => $parStatut,
            'total' => ['n' => (int) $parStatut->sum('n'), 'montant' => (float) $parStatut->sum('total')],
            'avancement' => $this->avancement($besoins->getCollection()),
            'attentes' => $this->attentes($besoins->getCollection(), $ouvertes, $user),
            'enAttenteMoi' => $ouvertes->filter(fn (WorkflowTache $t) => $this->taches->peutTraiter($t, $user))->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function filtres(Request $request, User $user): array
    {
        $parPage = (int) $request->query('par_page', 20);

        return [
            'q' => trim((string) $request->query('q', '')),
            'statut' => (string) $request->query('statut', ''),
            'priorite' => (string) $request->query('priorite', ''),
            'categorie' => (string) $request->query('categorie', ''),
            'structure_id' => (string) $request->query('structure_id', ''),
            'exercice_id' => (string) $request->query('exercice_id', ''),
            'tache_id' => (string) $request->query('tache_id', ''),
            'du' => (string) $request->query('du', ''),
            'au' => (string) $request->query('au', ''),
            'mes' => $request->boolean('mes'),
            'tri' => array_key_exists((string) $request->query('tri'), self::TRIS) ? (string) $request->query('tri') : 'recent',
            'par_page' => in_array($parPage, [20, 50, 100], true) ? $parPage : 20,
        ];
    }

    private function appliquerFiltres(Builder $q, array $f, User $user): void
    {
        if ($f['q'] !== '') {
            $like = '%'.mb_strtoupper($f['q']).'%';
            $q->where(fn (Builder $w) => $w->whereRaw('UPPER(numero) LIKE ?', [$like])
                ->orWhereRaw('UPPER(COALESCE(objet, \'\')) LIKE ?', [$like])
                ->orWhereHas('demandeur', fn (Builder $d) => $d->whereRaw('UPPER(name) LIKE ?', [$like]))
                ->orWhereHas('engagement', fn (Builder $e) => $e->whereRaw('UPPER(numero) LIKE ?', [$like])->orWhereRaw('UPPER(COALESCE(beneficiaire, \'\')) LIKE ?', [$like])));
        }
        foreach (['priorite', 'categorie', 'structure_id', 'exercice_id', 'tache_id'] as $champ) {
            if ($f[$champ] !== '') {
                $q->where($champ, $f[$champ]);
            }
        }
        if ($f['du'] !== '') {
            $q->whereDate('created_at', '>=', $f['du']);
        }
        if ($f['au'] !== '') {
            $q->whereDate('created_at', '<=', $f['au']);
        }
        if ($f['mes']) {
            $q->where('demandeur_id', $user->id);
        }
    }

    /**
     * Étape atteinte dans la chaîne et montant payé, par besoin de la page (3 requêtes groupées).
     *
     * @return array<int, array{etape: string, paye: float, engage: float}>
     */
    private function avancement(Collection $besoins): array
    {
        $engagements = $besoins->pluck('engagement')->filter();
        if ($engagements->isEmpty()) {
            return [];
        }
        $ids = $engagements->pluck('id')->all();
        $liq = DB::table('liquidations')->whereIn('engagement_id', $ids)->whereNotIn('statut', self::EXCLUS)->whereNull('deleted_at')
            ->groupBy('engagement_id')->selectRaw('engagement_id, count(*) as n')->pluck('n', 'engagement_id');
        $ord = DB::table('ordonnancements as o')->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->whereIn('l.engagement_id', $ids)->whereNotIn('o.statut', self::EXCLUS)->whereNull('o.deleted_at')
            ->groupBy('l.engagement_id')->selectRaw('l.engagement_id, count(*) as n')->pluck('n', 'engagement_id');
        $pay = DB::table('paiements as p')->join('ordonnancements as o', 'o.id', '=', 'p.ordonnancement_id')->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->whereIn('l.engagement_id', $ids)->where('p.statut', 'valide')->whereNull('p.deleted_at')
            ->where(fn ($e) => $e->whereNull('p.etat_execution')->orWhere('p.etat_execution', '!=', 'instrument_rejete'))
            ->groupBy('l.engagement_id')->selectRaw('l.engagement_id, sum(p.montant) as n')->pluck('n', 'engagement_id');

        return $engagements->mapWithKeys(fn ($e) => [$e->expression_besoin_id => [
            'etape' => match (true) {
                isset($pay[$e->id]) => 'PAY',
                isset($ord[$e->id]) => 'ORD',
                isset($liq[$e->id]) => 'LIQ',
                default => 'ENG',
            },
            'paye' => (float) ($pay[$e->id] ?? 0),
            'engage' => (float) $e->montant_total,
        ]])->all();
    }

    /** @return array<int, array{libelle: string, acteur: string, en_retard: bool, pour_moi: bool, echeance: mixed}> */
    private function attentes(Collection $besoins, Collection $ouvertes, User $user): array
    {
        $taches = $ouvertes->whereIn('objet_id', $besoins->modelKeys())->unique('objet_id');
        $roles = $taches->isEmpty() ? collect() : DB::table('roles')->pluck('libelle', 'name');

        return $taches->mapWithKeys(fn (WorkflowTache $t) => [$t->objet_id => [
            'libelle' => $t->libelle_etape ?: ($roles[$t->role] ?? $t->role),
            'acteur' => $t->assigne?->name ?? ($roles[$t->role] ?? str_replace('_', ' ', (string) $t->role)),
            'en_retard' => $t->enRetard(),
            'echeance' => $t->echeance_le,
            'pour_moi' => $this->taches->peutTraiter($t, $user),
        ]])->all();
    }
}
