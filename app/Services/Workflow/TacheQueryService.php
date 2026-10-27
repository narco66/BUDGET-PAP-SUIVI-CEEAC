<?php

namespace App\Services\Workflow;

use App\Contracts\ConcernantStructureWorkflow;
use App\Models\Delegation;
use App\Models\Interim;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTache;
use App\Services\AccessScopeService;
use App\Services\Institution\WorkflowInstitutionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Consultation « Mes Tâches » : files de travail, filtres, indicateurs et supervision.
 * L'éligibilité reprend les règles du moteur (rôle ou suppléance organique, séparation des fonctions,
 * contrôle Direction du Budget) ; l'administrateur n'est pas placé dans la file sans le rôle de l'étape.
 */
class TacheQueryService
{
    public const VUES = ['mes', 'unite', 'deleguees', 'traitees', 'dossiers'];

    /** @var array<int, array{ok: bool, delegue: bool, motif: ?string}> */
    private array $cacheEligibilite = [];

    public function __construct(
        private readonly SodPolicyService $sod,
        private readonly WorkflowInstitutionResolver $institution,
        private readonly AccessScopeService $scopes,
        private readonly TacheWorkflowService $taches,
        private readonly AffectationTacheService $affectations,
    ) {}

    /** @return array{ok: bool, delegue: bool, motif: ?string} */
    public function eligibilite(WorkflowTache $tache, User $user): array
    {
        $cle = $tache->id.':'.$user->id.':'.$tache->version;
        if (isset($this->cacheEligibilite[$cle])) {
            return $this->cacheEligibilite[$cle];
        }

        $refus = fn (string $m) => ['ok' => false, 'delegue' => false, 'motif' => $m];
        if ($tache->estPreparation()) {
            return $this->cacheEligibilite[$cle] = $this->eligibilitePreparation($tache, $user, $refus);
        }
        $etape = $tache->etape;
        $instance = $tache->instance;
        if (! $tache->estOuverte() || ! $etape || $etape->statut !== 'en_attente' || $instance?->statut !== 'en_cours') {
            return $this->cacheEligibilite[$cle] = $refus('Tâche close ou étape déjà traitée.');
        }
        if ($tache->assigne_a && (int) $tache->assigne_a !== $user->id) {
            return $this->cacheEligibilite[$cle] = $refus('Tâche prise en charge par un autre agent.');
        }

        // Règle d'affectation partagée avec les notifications et le moteur : titulaire organique de
        // l'étape ou son suppléant (à défaut de titulaire paramétré, tout porteur du rôle).
        $objet = $tache->objet;
        $structureId = $objet instanceof ConcernantStructureWorkflow ? $objet->structureIdWorkflow() : $tache->structure_id;
        if (! $this->affectations->estTitulaire($user, $tache->role, $structureId)) {
            return $this->cacheEligibilite[$cle] = $refus($this->motifHorsAffectation($tache));
        }
        $delegue = ! $user->hasRole($tache->role);

        $motif = $this->sod->motifRefus($instance, $user, $etape);

        return $this->cacheEligibilite[$cle] = ['ok' => $motif === null, 'delegue' => $delegue, 'motif' => $motif];
    }

    /**
     * Tâche de préparation (soumettre, corriger, préparer, solder) : ouverte aux titulaires de l'action ;
     * elle se clôt quand le dossier avance, pas par une décision prise dans « Mes Tâches ».
     *
     * @return array{ok: bool, delegue: bool, motif: ?string}
     */
    private function eligibilitePreparation(WorkflowTache $tache, User $user, \Closure $refus): array
    {
        if (! $tache->estOuverte()) {
            return $refus("Tâche close : l'action a déjà été réalisée.");
        }
        if ($tache->assigne_a && (int) $tache->assigne_a !== $user->id) {
            return $refus('Tâche prise en charge par un autre agent.');
        }
        if (! $this->affectations->estTitulaire($user, $tache->role, $tache->structure_id)) {
            return $refus($this->motifHorsAffectation($tache));
        }

        return ['ok' => true, 'delegue' => ! $user->hasRole($tache->role), 'motif' => null];
    }

    private function motifHorsAffectation(WorkflowTache $tache): string
    {
        $unite = $tache->structure?->code;

        return 'Action réservée au rôle « '.$tache->role.' »'.($unite ? " de l'unité {$unite}" : '').'.';
    }

    public function peutTraiter(WorkflowTache $tache, User $user): bool
    {
        return $this->eligibilite($tache, $user)['ok'];
    }

    public function peutVoirUnite(User $user): bool
    {
        return $user->can('workflow.view') || $user->can('workflow.audit')
            || $user->hasAnyRole(['chef_service', 'directeur', 'commissaire', 'secretaire_general', 'president']);
    }

    public function peutSuperviser(User $user): bool
    {
        return $user->can('workflow.audit') || $user->can('workflow.admin')
            || $user->hasAnyRole(['secretaire_general', 'president', 'auditeur']);
    }

    /**
     * Gestion des affectations (réaffecter, prioriser) : plus restreinte que la consultation de
     * supervision — administration du workflow, SG, Président, ou responsable de l'unité concernée.
     */
    public function peutGererAffectations(WorkflowTache $tache, User $user): bool
    {
        if ($user->can('workflow.admin') || $user->hasAnyRole(['secretaire_general', 'president'])) {
            return true;
        }
        $structure = $tache->structure;
        while ($structure) {
            if ((int) $structure->responsable_user_id === $user->id) {
                return true;
            }
            $structure = $structure->parent;
        }

        return false;
    }

    /** Peut consulter la tâche (traitant, acteur passé, initiateur, unité, supervision). */
    public function peutConsulter(WorkflowTache $tache, User $user): bool
    {
        if ($this->peutTraiter($tache, $user) || (int) $tache->termine_par === $user->id || (int) $tache->assigne_a === $user->id) {
            return true;
        }
        if ($tache->evenements()->where('user_id', $user->id)->exists()) {
            return true;
        }
        if ($this->estInitiateur($tache->instance, $user) || $this->peutSuperviser($user)) {
            return true;
        }

        // Vue d'unité : dans le périmètre organique ; une tâche sans unité n'est visible que des profils globaux.
        return $this->peutVoirUnite($user)
            && ($tache->structure_id ? $this->scopes->dansPerimetre($user, $tache->structure_id) : $this->scopes->estGlobal($user));
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function lister(User $user, string $vue, array $filtres = [], int $parPage = 20, int $page = 1): LengthAwarePaginator
    {
        $taches = $this->filtrerPhp($this->collection($user, $vue, $filtres), $user, $filtres);
        // Par défaut, les dernières tâches reçues en tête, tous processus confondus (tri avant pagination).
        $taches = $this->trier($taches, (string) ($filtres['tri'] ?? 'reception'), (string) ($filtres['sens'] ?? 'desc'));

        return new LengthAwarePaginator(
            $taches->forPage($page, $parPage)->values(),
            $taches->count(),
            $parPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /** @return Collection<int, WorkflowTache> */
    /**
     * @param  Collection<int, WorkflowTache>|null  $ouvertes  tâches ouvertes déjà chargées (mêmes filtres SQL) :
     *                                                       évite de les recharger pour chaque onglet (compteurs)
     */
    public function collection(User $user, string $vue, array $filtres = [], ?Collection $ouvertes = null): Collection
    {
        $q = $this->requeteBase($filtres);
        if ($vue === 'traitees') {
            return $q->where('termine_par', $user->id)->latest('termine_le')->limit(1000)->get();
        }

        return match ($vue) {
            'unite' => ! $this->peutVoirUnite($user) ? collect()
                : ($ouvertes === null
                    ? $q->ouvertes()->when(! $this->scopes->estGlobal($user), fn ($b) => $b->whereIn('structure_id', $this->scopes->structuresAutorisees($user)))->get()
                    : ($this->scopes->estGlobal($user) ? $ouvertes
                        : $ouvertes->whereIn('structure_id', $this->scopes->structuresAutorisees($user)->all())->values())),
            'dossiers' => ($ouvertes ?? $q->ouvertes()->get())->filter(fn (WorkflowTache $t) => $this->estInitiateur($t->instance, $user))->values(),
            'deleguees' => $this->candidates($user, $q, $ouvertes)->filter(fn (WorkflowTache $t) => $this->eligibilite($t, $user)['ok'] && $this->eligibilite($t, $user)['delegue'])->values(),
            default => $this->candidates($user, $q, $ouvertes)->filter(fn (WorkflowTache $t) => $this->peutTraiter($t, $user))->values(),
        };
    }

    private function requeteBase(array $filtres): Builder
    {
        $q = WorkflowTache::query()->with(['instance.workflow', 'etape', 'objet', 'structure', 'assigne', 'terminePar']);
        $this->appliquerFiltresSql($q, $filtres);

        return $q;
    }

    /**
     * Effectif de chaque onglet, calculé avec les mêmes règles que la liste affichée (les compteurs
     * et le contenu des onglets concordent toujours).
     *
     * @return array<string, int>
     */
    public function compteurs(User $user, array $filtres = []): array
    {
        $vues = array_filter(self::VUES, fn ($v) => $v !== 'unite' || $this->peutVoirUnite($user));

        // Tâches ouvertes chargées une seule fois pour tous les onglets (au lieu d'un chargement par onglet).
        $ouvertes = $this->requeteBase($filtres)->ouvertes()->get();

        return collect($vues)->mapWithKeys(fn ($v) => [$v => $this->filtrerPhp($this->collection($user, $v, $filtres, $ouvertes), $user, $filtres)->count()])->all();
    }

    /** Libellé « vu de l'initiateur » d'une tâche ouverte : où en est son dossier. */
    public function situationDossier(WorkflowTache $tache): string
    {
        $chez = $tache->assigne?->name ?? ($tache->libelle_etape ?: $tache->role);

        return match ($tache->statut) {
            'en_cours' => 'En cours chez '.$chez,
            'suspendue' => 'Suspendu chez '.$chez,
            default => 'En attente : '.$chez,
        };
    }

    /** @return array<string, int|float|null> */
    public function statistiques(User $user, ?string $depuis = null): array
    {
        $depuis = $depuis ? now()->parse($depuis)->startOfDay() : now()->startOfMonth();
        $mes = $this->collection($user, 'mes');
        $traitees = WorkflowTache::query()->where('termine_par', $user->id)->where('termine_le', '>=', $depuis)->get();
        $dansDelais = $traitees->filter(fn ($t) => ! $t->echeance_le || $t->termine_le->lte($t->echeance_le))->count();
        $delais = $traitees->map(fn ($t) => $t->created_at->diffInMinutes($t->termine_le) / 60);
        $deleguees = $traitees->isEmpty() ? 0 : \App\Models\WorkflowTacheEvenement::query()
            ->whereIn('workflow_tache_id', $traitees->pluck('id'))->where('type', 'decision')->whereNotNull('pour_compte_de')->count();

        return [
            'a_traiter' => $mes->where('statut', 'a_traiter')->count(),
            'en_cours' => $mes->where('statut', 'en_cours')->count(),
            'suspendues' => $mes->where('statut', 'suspendue')->count(),
            'urgentes' => $mes->filter(fn ($t) => in_array($this->taches->prioriteEffective($t), ['haute', 'urgente'], true))->count(),
            'en_retard' => $mes->filter->enRetard()->count(),
            'recues_periode' => $mes->filter(fn ($t) => $t->created_at->gte($depuis))->count() + $traitees->count(),
            'terminees_periode' => $traitees->count(),
            'retournees_periode' => $traitees->where('decision', 'renvoye')->count(),
            'deleguees_periode' => $deleguees,
            'delai_moyen_heures' => $delais->isEmpty() ? null : round($delais->avg(), 1),
            'taux_dans_delais' => $traitees->isEmpty() ? null : round($dansDelais / $traitees->count() * 100, 1),
            'depuis' => $depuis->toDateString(),
        ];
    }

    /** @return array<string, mixed> agrégats de supervision (périmètre de l'utilisateur) */
    public function supervision(User $user, ?string $depuis = null): array
    {
        $depuis = $depuis ? now()->parse($depuis)->startOfDay() : now()->startOfMonth();
        $q = WorkflowTache::query()->with('terminePar', 'structure');
        if (! $this->scopes->estGlobal($user)) {
            $q->where(fn (Builder $b) => $b->whereNull('structure_id')->orWhereIn('structure_id', $this->scopes->structuresAutorisees($user)));
        }
        $ouvertes = (clone $q)->ouvertes()->get();
        $terminees = (clone $q)->where('statut', 'terminee')->where('termine_le', '>=', $depuis)->get();
        $delais = fn (Collection $c) => $c->map(fn ($t) => $t->created_at->diffInMinutes($t->termine_le) / 60)->sort()->values();
        $mediane = function (Collection $v) {
            $n = $v->count();

            return $n === 0 ? null : round($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2, 1);
        };

        return [
            'ouvertes' => $ouvertes->count(),
            'en_retard' => $ouvertes->filter->enRetard()->count(),
            'terminees_periode' => $terminees->count(),
            'par_module' => $ouvertes->countBy('module')->sortDesc()->all(),
            'par_role' => $ouvertes->countBy('role')->sortDesc()->all(),
            'par_unite' => $ouvertes->countBy(fn ($t) => $t->structure?->code ?? '—')->sortDesc()->all(),
            // Anomalies d'affectation : aucun titulaire organique paramétré pour le rôle dans l'unité concernée.
            'affectations_defaut' => $ouvertes->where('affectation_defaut', true)->countBy(fn ($t) => $t->role.' · '.($t->structure?->code ?? '—'))->sortDesc()->all(),
            'retards_par_role' => $ouvertes->filter->enRetard()->countBy('role')->sortDesc()->all(),
            'decisions' => $terminees->countBy('decision')->all(),
            'par_acteur' => $terminees->countBy(fn ($t) => $t->terminePar?->name ?? '—')->sortDesc()->all(),
            'delais_par_etape' => $terminees->groupBy(fn ($t) => $t->module.' · '.$t->role)->map(fn ($g) => [
                'nombre' => $g->count(),
                'moyen_heures' => round($delais($g)->avg() ?? 0, 1),
                'median_heures' => $mediane($delais($g)),
            ])->all(),
            'depuis' => $depuis->toDateString(),
        ];
    }

    public function montant(WorkflowTache $tache): ?float
    {
        $o = $tache->objet;
        foreach (['montant_total', 'montant', 'montant_estime', 'montant_global'] as $champ) {
            if ($o && isset($o->{$champ})) {
                return (float) $o->{$champ};
            }
        }

        return null;
    }

    /** Initiateur du dossier : celui du circuit (étape) ou, hors circuit, le demandeur / créateur du dossier. */
    public function initiateur(WorkflowTache $tache): ?User
    {
        if ($tache->instance) {
            $id = $this->sod->resolveInitiateur($tache->instance) ?? $tache->instance->demarre_par;
        } else {
            $o = $tache->objet;
            $id = $o?->demandeur_id ?? $o?->cree_par ?? $o?->created_by ?? $o?->saisi_par ?? null;
        }

        return $id ? User::query()->find($id) : null;
    }

    public function estInitiateur(?WorkflowInstance $instance, User $user): bool
    {
        if (! $instance) {
            return false;
        }

        return (int) $instance->demarre_par === $user->id || $this->sod->resolveInitiateur($instance) === $user->id;
    }

    /** Tâches ouvertes dont le rôle est porté par l'utilisateur ou par un titulaire qu'il supplée. */
    private function candidates(User $user, Builder $q, ?Collection $ouvertes = null): Collection
    {
        $roles = $user->getRoleNames()->all();
        $titulaires = Delegation::query()->enCours()->where('delegue_a', $user->id)->pluck('delegue_par')
            ->merge(Interim::query()->enCours()->where('interim_user_id', $user->id)->pluck('titulaire_user_id'))->unique();
        if ($titulaires->isNotEmpty()) {
            $roles = array_merge($roles, User::query()->whereIn('id', $titulaires)->get()->flatMap(fn (User $u) => $u->getRoleNames())->all());
        }

        $roles = array_values(array_unique($roles));

        return $ouvertes === null
            ? $q->ouvertes()->whereIn('role', $roles)->get()
            : $ouvertes->whereIn('role', $roles)->values();
    }

    private function appliquerFiltresSql(Builder $q, array $f): void
    {
        $q->when($f['module'] ?? null, fn ($b, $v) => $b->where('module', $v))
            ->when($f['etape'] ?? null, fn ($b, $v) => $b->where('role', $v))
            ->when($f['statut'] ?? null, fn ($b, $v) => $b->where('statut', $v))
            ->when($f['decision'] ?? null, fn ($b, $v) => $b->where('decision', $v))
            ->when($f['structure_id'] ?? null, fn ($b, $v) => $b->where('structure_id', (int) $v))
            ->when($f['du'] ?? null, fn ($b, $v) => $b->where('created_at', '>=', now()->parse($v)->startOfDay()))
            ->when($f['au'] ?? null, fn ($b, $v) => $b->where('created_at', '<=', now()->parse($v)->endOfDay()))
            ->when($f['q'] ?? null, function ($b, $v) {
                $terme = '%'.mb_strtolower(trim($v)).'%';
                $b->where(fn ($w) => $w->whereRaw('LOWER(reference) LIKE ?', [$terme])
                    ->orWhereRaw('LOWER(objet_libelle) LIKE ?', [$terme])
                    ->orWhereRaw('LOWER(workflow_code) LIKE ?', [$terme])
                    ->orWhereRaw('LOWER(libelle_etape) LIKE ?', [$terme]));
            });
    }

    private function filtrerPhp(Collection $taches, User $user, array $f): Collection
    {
        return $taches
            ->when($f['priorite'] ?? null, fn ($c, $v) => $c->filter(fn ($t) => $this->taches->prioriteEffective($t) === $v))
            ->when($f['echeance'] ?? null, fn ($c, $v) => $c->filter(fn ($t) => match ($v) {
                'retard' => $t->enRetard(),
                'aujourdhui' => $t->echeance_le?->isToday(),
                'semaine' => $t->echeance_le && $t->echeance_le->between(now(), now()->addWeek()),
                default => true,
            }))
            ->when(($f['affectation'] ?? null) === 'moi', fn ($c) => $c->filter(fn ($t) => (int) $t->assigne_a === $user->id))
            ->when(($f['affectation'] ?? null) === 'libre', fn ($c) => $c->filter(fn ($t) => $t->assigne_a === null))
            ->when(isset($f['montant_min']) && $f['montant_min'] !== '', fn ($c) => $c->filter(fn ($t) => ($this->montant($t) ?? 0) >= (float) $f['montant_min']))
            ->when(isset($f['montant_max']) && $f['montant_max'] !== '', fn ($c) => $c->filter(fn ($t) => ($this->montant($t) ?? 0) <= (float) $f['montant_max']))
            ->values();
    }

    private function trier(Collection $taches, string $tri, string $sens): Collection
    {
        $rang = array_flip(array_keys(WorkflowTache::PRIORITES));
        $cle = match ($tri) {
            'reception' => fn ($t) => $t->created_at?->timestamp ?? 0,
            'priorite' => fn ($t) => $rang[$this->taches->prioriteEffective($t)] ?? 0,
            'montant' => fn ($t) => $this->montant($t) ?? 0,
            'termine' => fn ($t) => $t->termine_le?->timestamp ?? 0,
            default => fn ($t) => $t->echeance_le?->timestamp ?? PHP_INT_MAX,
        };

        // Critère secondaire stable (identifiant) : l'ordre ne varie pas d'une page à l'autre.
        $signe = $sens === 'desc' ? -1 : 1;

        return $taches->sort(fn ($a, $b) => $signe * ($cle($a) <=> $cle($b)) ?: $signe * ($a->id <=> $b->id))->values();
    }
}
