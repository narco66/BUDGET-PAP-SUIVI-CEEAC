<?php

namespace App\Services\Workflow;

use App\Models\AuditWorkflow;
use App\Models\User;
use App\Models\VisaElectronique;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Models\WorkflowTache;
use App\Services\AccessScopeService;
use App\Support\Horodatage;
use App\Support\WorkflowObjetUrls;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pilotage des circuits de validation (référentiel Workflow § 21) : instances actives, délais réels de traitement
 * (activation de la tâche → décision), respect des délais, rejets et retours, dossiers bloqués, goulots par étape,
 * charge par rôle et par unité, incidents techniques. Toutes les valeurs sont calculées sur les données enregistrées.
 */
class WorkflowDashboardService
{
    /** Sans décision depuis ce nombre de jours, un dossier en cours est considéré comme bloqué. */
    public const SEUIL_BLOCAGE_JOURS = 10;

    public function __construct(private readonly AccessScopeService $scopes) {}

    /** @return array<string, mixed> */
    public function tableau(?int $jours = 90, ?string $module = null, ?string $circuit = null, ?User $user = null): array
    {
        $jours = $jours ?: 90;
        $depuis = now()->subDays($jours);
        $codes = $this->codesCircuits($module, $circuit);

        $instances = WorkflowInstance::query()->whereHas('workflow', fn ($q) => $q->whereIn('code', $codes));
        $enCours = (clone $instances)->where('statut', 'en_cours')->count();
        $valides = (clone $instances)->where('statut', 'valide')->where('updated_at', '>=', $depuis)->count();
        $rejetes = (clone $instances)->where('statut', 'rejete')->where('updated_at', '>=', $depuis)->count();

        // Tâches : unité de travail mesurée de l'activation à la décision ; périmètre organique appliqué au détail.
        $taches = WorkflowTache::query()->whereIn('workflow_code', $codes)
            ->when($user && ! $this->scopes->estGlobal($user), fn ($q) => $q->where(fn ($w) => $w->whereNull('structure_id')
                ->orWhereIn('structure_id', $this->scopes->structuresAutorisees($user))));
        $ouvertes = (clone $taches)->ouvertes()->with(['structure', 'assigne'])->get();
        $terminees = (clone $taches)->where('statut', 'terminee')->where('termine_le', '>=', $depuis)->get();

        $delais = $terminees->map(fn ($t) => $t->created_at->diffInMinutes($t->termine_le) / 60)->sort()->values();
        $avecEcheance = $terminees->filter(fn ($t) => $t->echeance_le !== null);
        $dansDelais = $avecEcheance->filter(fn ($t) => $t->termine_le->lte($t->echeance_le))->count();
        $retours = $terminees->where('decision', 'renvoye')->count();
        $enRetard = $ouvertes->filter->enRetard();

        $etapesEnRetard = WorkflowInstanceEtape::query()
            ->whereHas('instance', fn ($q) => $q->where('statut', 'en_cours')->whereHas('workflow', fn ($w) => $w->whereIn('code', $codes)))
            ->where('statut', 'en_attente')->whereNotNull('echeance_le')->where('echeance_le', '<', now())->count();

        $bloques = (clone $instances)->where('statut', 'en_cours')
            ->where('updated_at', '<', now()->subDays(self::SEUIL_BLOCAGE_JOURS))->count();

        $parCircuit = Workflow::query()->whereIn('code', $codes)->withCount([
            'instances as en_cours_count' => fn ($q) => $q->where('statut', 'en_cours'),
            'instances as valides_count' => fn ($q) => $q->where('statut', 'valide')->where('updated_at', '>=', $depuis),
            'instances as rejetes_count' => fn ($q) => $q->where('statut', 'rejete')->where('updated_at', '>=', $depuis),
        ])->orderBy('code')->get()->each(function (Workflow $w) use ($terminees, $ouvertes) {
            $t = $terminees->where('workflow_code', $w->code);
            $avec = $t->filter(fn ($x) => $x->echeance_le !== null);
            $w->delai_moyen_heures = $t->isEmpty() ? null : round($t->avg(fn ($x) => $x->created_at->diffInMinutes($x->termine_le) / 60), 1);
            $w->taux_sla = $avec->isEmpty() ? null : round($avec->filter(fn ($x) => $x->termine_le->lte($x->echeance_le))->count() / $avec->count() * 100, 1);
            $w->en_retard_count = $ouvertes->where('workflow_code', $w->code)->filter->enRetard()->count();
            $clos = $w->valides_count + $w->rejetes_count;
            $w->taux_rejet = $clos > 0 ? round($w->rejetes_count / $clos * 100, 1) : null;
        });

        return [
            'periode_jours' => $jours,
            'filtres' => ['module' => $module, 'circuit' => $circuit],
            'kpi' => [
                'en_cours' => $enCours,
                'en_retard' => $etapesEnRetard,
                'valides_periode' => $valides,
                'rejetes_periode' => $rejetes,
                'taux_rejet_pct' => round($rejetes / max(1, $valides + $rejetes) * 100, 1),
                'delai_moyen_jours' => $delais->isEmpty() ? null : round($delais->avg() / 24, 1),
                'delai_moyen_heures' => $delais->isEmpty() ? null : round($delais->avg(), 1),
                'delai_median_heures' => $this->mediane($delais),
                'taux_sla_pct' => $avecEcheance->isEmpty() ? null : round($dansDelais / $avecEcheance->count() * 100, 1),
                'decisions_periode' => $terminees->count(),
                'retours_periode' => $retours,
                'taux_retour_pct' => $terminees->isEmpty() ? null : round($retours / $terminees->count() * 100, 1),
                'bloques' => $bloques,
                'taches_ouvertes' => $ouvertes->count(),
                'taches_en_retard' => $enRetard->count(),
                'visas_emis' => VisaElectronique::where('created_at', '>=', $depuis)->count(),
                'incidents_techniques' => $this->incidentsTechniques($depuis),
            ],
            'par_circuit' => $parCircuit,
            'goulots' => $this->goulots($ouvertes, $terminees),
            'charge_roles' => $ouvertes->groupBy('role')->map(fn ($g, $role) => [
                'role' => $role, 'ouvertes' => $g->count(), 'en_retard' => $g->filter->enRetard()->count(),
            ])->sortByDesc('ouvertes')->values(),
            'charge_unites' => $ouvertes->groupBy(fn ($t) => $t->structure?->code ?? '—')->map(fn ($g, $code) => [
                'unite' => $code, 'libelle' => $g->first()->structure?->libelle, 'ouvertes' => $g->count(), 'en_retard' => $g->filter->enRetard()->count(),
            ])->sortByDesc('ouvertes')->values()->take(10),
            'retards' => $enRetard->sortBy('echeance_le')->take(12)->map(fn (WorkflowTache $t) => [
                'tache' => $t,
                'jours' => (int) $t->echeance_le->diffInDays(now()),
                'url' => route('taches.show', $t, false),
                'libelle' => $t->objet_libelle,
            ])->values(),
            'evolution' => $this->evolution($terminees, $depuis),
            'dernieres_actions' => AuditWorkflow::query()->with('acteur')
                ->whereIn('workflow_code', $codes)->latest('created_at')->limit(15)->get()
                ->tap(fn ($actions) => $this->prechargerObjets($actions))
                ->each(function ($a) {
                    $objet = $this->objet($a->objet_type, $a->objet_id);
                    $a->url_objet = $objet ? WorkflowObjetUrls::show($objet) : null;
                    $a->libelle_objet = $objet ? ($objet->numero ?? $objet->reference ?? WorkflowObjetUrls::libelle($objet)) : null;
                }),
            'modules' => WorkflowTache::query()->distinct()->orderBy('module')->pluck('module')->values(),
            'circuits' => Workflow::query()->orderBy('code')->pluck('libelle', 'code'),
            'seuil_blocage_jours' => self::SEUIL_BLOCAGE_JOURS,
        ];
    }

    /** @return Collection<int, array<int, string>> */
    public function exportCsvLignes(?int $jours = 90): Collection
    {
        $depuis = now()->subDays($jours ?? 90);

        return AuditWorkflow::query()
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                Horodatage::local($a->created_at, 'Y-m-d H:i:s'),
                $a->workflow_code,
                $a->action,
                $a->etape_ordre,
                $a->acteur?->name,
                class_basename((string) $a->objet_type),
                $a->objet_id,
                $a->observation,
            ]);
    }

    /** @return list<string> codes de circuits retenus par les filtres */
    private function codesCircuits(?string $module, ?string $circuit): array
    {
        return Workflow::query()->pluck('code')
            ->filter(fn ($code) => (! $circuit || $code === $circuit) && (! $module || TacheWorkflowService::module($code) === $module))
            ->values()->all();
    }

    /** Goulots d'étranglement : étapes classées par retards puis par volume en attente. */
    private function goulots(Collection $ouvertes, Collection $terminees): Collection
    {
        $cle = fn ($t) => $t->workflow_code.'|'.$t->etape_ordre;

        return $ouvertes->concat($terminees)->groupBy($cle)->map(function (Collection $g) {
            $t = $g->first();
            $finies = $g->where('statut', 'terminee');
            $delais = $finies->map(fn ($x) => $x->created_at->diffInMinutes($x->termine_le) / 60)->sort()->values();
            $avec = $finies->filter(fn ($x) => $x->echeance_le !== null);

            return [
                'circuit' => $t->workflow_code,
                'etape' => $t->etape_ordre,
                'libelle' => $t->libelle_etape ?: $t->role,
                'role' => $t->role,
                'en_attente' => $g->filter->estOuverte()->count(),
                'en_retard' => $g->filter->enRetard()->count(),
                'traitees' => $finies->count(),
                'delai_moyen_heures' => $delais->isEmpty() ? null : round($delais->avg(), 1),
                'delai_median_heures' => $this->mediane($delais),
                'taux_sla' => $avec->isEmpty() ? null : round($avec->filter(fn ($x) => $x->termine_le->lte($x->echeance_le))->count() / $avec->count() * 100, 1),
            ];
        })->sortBy([['en_retard', 'desc'], ['en_attente', 'desc'], ['delai_moyen_heures', 'desc']])->values()->take(12);
    }

    /** Décisions par semaine (validées, rejetées, retournées) sur la période. */
    private function evolution(Collection $terminees, \DateTimeInterface $depuis): array
    {
        $semaines = [];
        for ($d = now()->parse($depuis)->startOfWeek(); $d->lte(now()); $d = $d->copy()->addWeek()) {
            $semaines[$d->format('Y-m-d')] = ['valide' => 0, 'rejete' => 0, 'renvoye' => 0];
        }
        foreach ($terminees as $t) {
            $s = $t->termine_le->copy()->startOfWeek()->format('Y-m-d');
            if (isset($semaines[$s][$t->decision])) {
                $semaines[$s][$t->decision]++;
            }
        }

        return [
            'semaines' => array_map(fn ($s) => now()->parse($s)->format('d/m'), array_keys($semaines)),
            'valide' => array_column($semaines, 'valide'),
            'rejete' => array_column($semaines, 'rejete'),
            'renvoye' => array_column($semaines, 'renvoye'),
        ];
    }

    /** Échecs techniques à réconcilier : tâches de file en échec, envois de notification en échec, refus tracés. */
    private function incidentsTechniques(\DateTimeInterface $depuis): array
    {
        return [
            'jobs_en_echec' => \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'notifications_en_echec' => DB::table('notification_logs')->where('created_at', '>=', $depuis)->where('statut', 'like', 'echec%')->count(),
            'decisions_refusees' => DB::table('journaux_audit')->where('created_at', '>=', $depuis)->whereIn('action', ['workflow_decision_refusee', 'sod_violation'])->count(),
        ];
    }

    /** @var array<string, ?\Illuminate\Database\Eloquent\Model> objets des dernières actions, chargés par type */
    private array $objets = [];

    /** Charge les objets des actions en une requête par type (au lieu d'une par action). */
    private function prechargerObjets(Collection $actions): void
    {
        foreach ($actions->groupBy('objet_type') as $type => $groupe) {
            $classe = $type ? (\Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type) ?? $type) : null;
            if (! $classe || ! class_exists($classe)) {
                continue;
            }
            $ids = $groupe->pluck('objet_id')->filter()->unique();
            $trouves = $classe::query()->whereIn((new $classe)->getKeyName(), $ids)->get()->keyBy(fn ($m) => $m->getKey());
            foreach ($ids as $id) {
                $this->objets[$type.'#'.$id] = $trouves[$id] ?? null;
            }
        }
    }

    private function objet(?string $type, ?int $id): ?\Illuminate\Database\Eloquent\Model
    {
        if (array_key_exists($type.'#'.$id, $this->objets)) {
            return $this->objets[$type.'#'.$id];
        }
        $classe = $type ? (\Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type) ?? $type) : null;

        return $classe && $id && class_exists($classe) ? $classe::query()->find($id) : null;
    }

    private function mediane(Collection $valeurs): ?float
    {
        $v = $valeurs->sort()->values();
        $n = $v->count();

        return $n === 0 ? null : round($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2, 1);
    }
}
