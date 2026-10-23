<?php

namespace App\Services\Workflow;

use App\Models\AuditWorkflow;
use App\Models\VisaElectronique;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use Illuminate\Support\Collection;

/** KPI performance des circuits D14 (P2). */
class WorkflowDashboardService
{
    /** @return array<string, mixed> */
    public function tableau(?int $jours = 90): array
    {
        $depuis = now()->subDays($jours ?? 90);

        $instances = WorkflowInstance::query()->with('workflow')->get();
        $enCours = $instances->where('statut', 'en_cours')->count();
        $valides = $instances->where('statut', 'valide')->where('updated_at', '>=', $depuis)->count();
        $rejetes = $instances->where('statut', 'rejete')->where('updated_at', '>=', $depuis)->count();

        $etapesEnRetard = WorkflowInstanceEtape::query()
            ->whereHas('instance', fn ($q) => $q->where('statut', 'en_cours'))
            ->where('statut', 'en_attente')
            ->whereNotNull('echeance_le')
            ->where('echeance_le', '<', now())
            ->count();

        $totalClos = max(1, $valides + $rejetes);
        $tauxRejet = round($rejetes / $totalClos * 100, 1);

        $delaiMoyen = WorkflowInstanceEtape::query()
            ->where('statut', 'valide')
            ->whereNotNull('decision_le')
            ->where('decision_le', '>=', $depuis)
            ->get()
            ->avg(fn ($e) => $e->decision_le && $e->echeance_le
                ? max(0, $e->echeance_le->diffInDays($e->decision_le, false))
                : null);

        $parCircuit = Workflow::withCount([
            'instances as en_cours_count' => fn ($q) => $q->where('statut', 'en_cours'),
            'instances as valides_count' => fn ($q) => $q->where('statut', 'valide')->where('updated_at', '>=', $depuis),
            'instances as rejetes_count' => fn ($q) => $q->where('statut', 'rejete')->where('updated_at', '>=', $depuis),
        ])->orderBy('code')->get();

        $dernieresActions = AuditWorkflow::query()
            ->with('acteur')
            ->latest('created_at')
            ->limit(15)
            ->get();

        return [
            'periode_jours' => $jours,
            'kpi' => [
                'en_cours' => $enCours,
                'en_retard' => $etapesEnRetard,
                'valides_periode' => $valides,
                'rejetes_periode' => $rejetes,
                'taux_rejet_pct' => $tauxRejet,
                'delai_moyen_jours' => $delaiMoyen !== null ? round((float) $delaiMoyen, 1) : null,
                'visas_emis' => VisaElectronique::where('created_at', '>=', $depuis)->count(),
            ],
            'par_circuit' => $parCircuit,
            'dernieres_actions' => $dernieresActions,
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
                $a->created_at?->format('Y-m-d H:i:s'),
                $a->workflow_code,
                $a->action,
                $a->etape_ordre,
                $a->acteur?->name,
                class_basename((string) $a->objet_type),
                $a->objet_id,
                $a->observation,
            ]);
    }
}
