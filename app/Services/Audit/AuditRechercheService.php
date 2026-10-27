<?php

namespace App\Services\Audit;

use App\Models\JournalAudit;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Recherche, statistiques et chronologies du journal, toujours limitées au périmètre de l'utilisateur. */
class AuditRechercheService
{
    public const FILTRES = ['du', 'au', 'categorie', 'module', 'action', 'resultat', 'gravite', 'acteur', 'reference', 'correlation_id', 'request_id', 'type', 'structure_id', 'delegation', 'q'];

    public function __construct(private readonly AuditAccesService $acces) {}

    public function requete(User $user, array $f): Builder
    {
        $fuseau = config('app.timezone_affichage', 'Africa/Libreville');
        $q = $this->acces->filtrer(JournalAudit::query()->with('user', 'titulaire'), $user);

        return $q
            // Les bornes saisies sont des dates locales (Libreville), converties en UTC pour la recherche.
            ->when($f['du'] ?? null, fn ($b, $v) => $b->where('created_at', '>=', Carbon::parse($v, $fuseau)->startOfDay()->utc()))
            ->when($f['au'] ?? null, fn ($b, $v) => $b->where('created_at', '<=', Carbon::parse($v, $fuseau)->endOfDay()->utc()))
            ->when($f['categorie'] ?? null, fn ($b, $v) => $b->where('categorie', $v))
            // Événements antérieurs au module : module déduit du type d'objet (aucune réécriture du journal).
            ->when($f['module'] ?? null, fn ($b, $v) => $b->where(fn ($c) => $c->where('module', $v)
                ->orWhere(fn ($d) => $d->whereNull('module')->whereIn('auditable_type', AuditLogger::classesDuModule($v)))))
            ->when($f['action'] ?? null, fn ($b, $v) => $b->where('action', $v))
            ->when($f['resultat'] ?? null, fn ($b, $v) => $b->where('resultat', $v))
            ->when($f['gravite'] ?? null, fn ($b, $v) => $b->where('gravite', $v))
            ->when($f['type'] ?? null, fn ($b, $v) => $b->where('auditable_type', $v))
            ->when($f['structure_id'] ?? null, fn ($b, $v) => $b->where('structure_id', (int) $v))
            ->when($f['correlation_id'] ?? null, fn ($b, $v) => $b->where('correlation_id', $v))
            ->when($f['request_id'] ?? null, fn ($b, $v) => $b->where('request_id', $v))
            ->when(($f['delegation'] ?? null) === '1', fn ($b) => $b->whereNotNull('pour_compte_de'))
            ->when($f['acteur'] ?? null, fn ($b, $v) => is_numeric($v) ? $b->where('user_id', (int) $v) : $b->where('user_nom', 'like', '%'.$v.'%'))
            ->when($f['reference'] ?? null, fn ($b, $v) => $b->where('reference_objet', 'like', '%'.$v.'%'))
            ->when($f['q'] ?? null, fn ($b, $v) => $b->where(fn ($c) => $c->where('code_evenement', 'like', '%'.strtoupper($v).'%')
                ->orWhere('reference_objet', 'like', '%'.$v.'%')->orWhere('user_nom', 'like', '%'.$v.'%')->orWhere('motif', 'like', '%'.$v.'%')));
    }

    /** @return array<string, mixed> indicateurs du périmètre (§ 4.1) */
    public function tableauDeBord(User $user, ?string $depuis = null): array
    {
        $depuis = $depuis ? Carbon::parse($depuis)->startOfDay() : now()->subDays(30)->startOfDay();
        $base = fn () => $this->acces->filtrer(JournalAudit::query(), $user)->where('created_at', '>=', $depuis);
        $compte = fn (string $col) => $base()->select($col, DB::raw('count(*) as n'))->groupBy($col)->pluck('n', $col)->map(fn ($n) => (int) $n)->all();
        $dernierControle = \App\Models\AuditControleIntegrite::query()->latest('id')->first();
        $dernierScellement = \App\Models\AuditScellement::query()->latest('a_id')->first();

        return [
            'depuis' => $depuis->toDateString(),
            'perimetre' => $this->acces->perimetre($user),
            'total' => $base()->count(),
            'par_resultat' => $compte('resultat'),
            'par_categorie' => $compte('categorie'),
            'par_module' => $this->parModule($base),
            'anterieurs' => $base()->whereNull('categorie')->count(),
            'par_gravite' => $compte('gravite'),
            'decisions' => $base()->whereIn('action', ['workflow_valide', 'workflow_rejete', 'workflow_renvoye'])->count(),
            'droits' => $base()->where(fn ($b) => $b->where('action', 'like', 'role_%')->orWhere('action', 'like', 'utilisateur_roles%')->orWhere('action', 'like', 'utilisateur_profils%'))->count(),
            'refus' => $base()->where('resultat', 'refus')->count(),
            'par_delegation' => $base()->whereNotNull('pour_compte_de')->count(),
            'alertes_ouvertes' => $this->acces->etendu($user) ? \App\Models\Alerte::query()->where('module', 'audit')->where('statut', 'ouverte')->count() : null,
            'dernier_controle' => $this->acces->etendu($user) ? $dernierControle : null,
            'dernier_scellement' => $this->acces->etendu($user) ? $dernierScellement : null,
            'non_scelles' => $this->acces->etendu($user) ? JournalAudit::query()->where('id', '>', (int) $dernierScellement?->a_id)->count() : null,
            'derniers' => $base()->with('user')->latest('id')->limit(8)->get(),
        ];
    }

    /** Répartition par module ; pour les événements antérieurs au module, déduite du type d'objet. */
    private function parModule(callable $base): array
    {
        $parModule = collect($base()->whereNotNull('module')->select('module', DB::raw('count(*) as n'))->groupBy('module')->pluck('n', 'module'))->map(fn ($n) => (int) $n);
        foreach ($base()->whereNull('module')->select('auditable_type', DB::raw('count(*) as n'))->groupBy('auditable_type')->pluck('n', 'auditable_type') as $type => $n) {
            $module = AuditLogger::moduleDuType($type ?: null) ?? 'AUTRE';
            $parModule[$module] = ($parModule[$module] ?? 0) + (int) $n;
        }

        return $parModule->sortDesc()->take(14)->all();
    }

    /**
     * Chronologie consolidée d'un dossier (§ 4, « Historique des dossiers ») : journal central, décisions du
     * moteur, événements des tâches, visas et GED, sans recopie entre journaux.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function chronologieDossier(User $user, string $type, int $id): Collection
    {
        $classe = str_contains($type, '\\') ? $type : 'App\\Models\\'.$type;
        $evenements = $this->acces->filtrer(JournalAudit::query()->with('user'), $user)
            ->where('auditable_type', $classe)->where('auditable_id', $id)->get()
            ->map(fn (JournalAudit $j) => [
                'instant' => $j->instant(), 'source' => 'Journal', 'code' => $j->code_evenement ?? strtoupper($j->action),
                'acteur' => $j->user_nom ?? 'Système', 'detail' => $j->motif, 'id' => $j->id,
            ]);

        // Les journaux spécialisés ne portent pas d'unité : ils ne sont joints que pour un accès étendu
        // ou lorsque l'utilisateur peut déjà consulter au moins un événement du dossier.
        if ($evenements->isNotEmpty() || $this->acces->etendu($user)) {
            $instances = DB::table('workflow_instances')->where('objet_type', $classe)->where('objet_id', $id)->pluck('id');
            $evenements = $evenements
                ->merge(DB::table('audit_workflow')->leftJoin('users', 'users.id', '=', 'audit_workflow.acteur_id')
                    ->whereIn('workflow_instance_id', $instances)->get(['audit_workflow.*', 'users.name'])
                    ->map(fn ($a) => ['instant' => Carbon::parse($a->created_at, 'UTC'), 'source' => 'Workflow', 'code' => strtoupper($a->action).($a->etape_ordre ? ' · étape '.$a->etape_ordre : ''),
                        'acteur' => $a->name ?? 'Système', 'detail' => $a->observation, 'id' => null]))
                ->merge(DB::table('workflow_tache_evenements as e')->join('workflow_taches as t', 't.id', '=', 'e.workflow_tache_id')
                    ->leftJoin('users as u', 'u.id', '=', 'e.user_id')->where('t.objet_type', $classe)->where('t.objet_id', $id)
                    ->get(['e.*', 't.reference', 'u.name'])
                    ->map(fn ($e) => ['instant' => Carbon::parse($e->created_at, 'UTC'), 'source' => 'Tâche '.$e->reference, 'code' => strtoupper($e->type),
                        'acteur' => $e->name ?? 'Système', 'detail' => $e->commentaire.($e->pour_compte_de ? ' (pour le compte du titulaire #'.$e->pour_compte_de.')' : ''), 'id' => null]))
                ->merge(DB::table('visas_electroniques as v')->leftJoin('users as u', 'u.id', '=', 'v.acteur_id')->whereIn('v.workflow_instance_id', $instances)
                    ->get(['v.*', 'u.name'])
                    ->map(fn ($v) => ['instant' => Carbon::parse($v->created_at, 'UTC'), 'source' => 'Visa', 'code' => 'VISA '.strtoupper($v->type_visa),
                        'acteur' => $v->name ?? '—', 'detail' => 'Empreinte '.substr($v->hash_contenu, 0, 16).'…', 'id' => null]));
        }

        return $evenements->sortBy(fn ($e) => $e['instant']?->getPreciseTimestamp(6) ?? 0)->values();
    }
}
