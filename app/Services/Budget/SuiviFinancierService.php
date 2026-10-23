<?php

namespace App\Services\Budget;

use App\Services\Depense\DepenseReportingService;
use App\Services\Pap\PapPerformanceService;
use App\Services\Reporting\ReportingService;
use App\Services\TableauBordService;

/** Orchestration du module Suivi Financier (phase P2). */
class SuiviFinancierService
{
    public function __construct(
        private readonly TableauBordService $tableauBord,
        private readonly ReportingService $reporting,
        private readonly SituationBudgetaireService $situations,
        private readonly CompteurMouvementAuditService $compteurs,
        private readonly PapPerformanceService $pap,
        private readonly DepenseReportingService $depense,
    ) {
    }

    /** @return array<string,mixed> */
    public function hub(int $exerciceId): array
    {
        $budget = $this->tableauBord->executionBudgetaire($exerciceId);
        $audit = $this->compteurs->analyser($exerciceId);

        return [
            'budget' => $budget,
            'parStructure' => $this->reporting->parStructure($exerciceId, 15),
            'parBailleur' => $this->reporting->parBailleur($exerciceId)['sources']->take(12),
            'parAxe' => $this->reporting->parAxe($exerciceId)->take(12),
            'parActivite' => $this->pap->performanceActivites($exerciceId)->take(15),
            'parTache' => $this->depense->executionParTache($exerciceId),
            'ecarts' => $this->pap->ecartsPhysiqueFinancier($exerciceId),
            'anomalies' => $audit,
            'anomalies_total' => $audit['total_anomalies'] ?? 0,
        ];
    }

    public function peutConsulter(?\App\Models\User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_financier.view')
            || $this->perm($user, 'tableau_bord.voir')
            || $this->perm($user, 'reporting.view')
            || $this->perm($user, 'rapport.voir')
        );
    }

    public function peutExporter(?\App\Models\User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_financier.export')
            || $this->perm($user, 'reporting.export')
            || $this->perm($user, 'rapport.exporter')
            || $this->perm($user, 'depenses.export')
        );
    }

    public function peutCapturer(?\App\Models\User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_financier.validate')
            || $this->perm($user, 'suivi_financier.admin')
        );
    }

    private function perm(\App\Models\User $user, string $permission): bool
    {
        return $user->hasPermissionTo($permission, 'web');
    }
}
