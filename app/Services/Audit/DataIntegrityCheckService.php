<?php

namespace App\Services\Audit;

use App\Models\AuditIntegriteData;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\Budget\BudgetAuditService;
use App\Services\Budget\CompteurMouvementAuditService;
use App\Services\Depense\DepenseAuditService;
use App\Services\Pap\PapAuditService;
use App\Services\Rbm\RbmAuditService;
use App\Services\Suivi\SuiviAuditService;
use Illuminate\Support\Facades\Schema;

/**
 * Contrôle d'intégrité transversal — agrège les audits métier existants (P2-10).
 */
class DataIntegrityCheckService
{
    /** @var array<string, int> */
    private const POIDS_NIVEAU = ['info' => 1, 'warning' => 5, 'critique' => 10];

    public function __construct(
        private readonly PapAuditService $pap,
        private readonly BudgetAuditService $budget,
        private readonly RbmAuditService $rbm,
        private readonly DepenseAuditService $depense,
        private readonly SuiviAuditService $suivi,
        private readonly CompteurMouvementAuditService $compteurs,
        private readonly StructureMasterDataAuditService $organisation,
    ) {}

    /**
     * @param  list<string>|null  $modules
     * @return array<string, mixed>
     */
    public function executer(?int $exerciceId = null, ?array $modules = null, bool $persister = false): array
    {
        $modules = $this->resoudreModules($modules);
        $resultats = [];
        $anomalies = [];

        if (in_array('transversal', $modules, true)) {
            $transversal = $this->auditerTransversal($exerciceId);
            $resultats['transversal'] = $this->resumeModule('transversal', $transversal);
            $anomalies = array_merge($anomalies, $this->etiqueter($transversal, 'transversal'));
        }

        if (in_array('organisation', $modules, true)) {
            $rapport = $this->organisation->executer($exerciceId);
            $resultats['organisation'] = $this->resumeModule('organisation', $rapport['anomalies'] ?? [], $rapport);
            $anomalies = array_merge($anomalies, $this->etiqueter($rapport['anomalies'] ?? [], 'organisation'));
        }

        if ($exerciceId) {
            if (in_array('pap', $modules, true)) {
                $rapport = $this->pap->executer($exerciceId);
                $resultats['pap'] = $this->resumeModule('pap', $rapport['anomalies'] ?? [], $rapport);
                $anomalies = array_merge($anomalies, $this->etiqueter($rapport['anomalies'] ?? [], 'pap'));
            }

            if (in_array('budget', $modules, true)) {
                $rapport = $this->budget->executer($exerciceId);
                $total = (int) ($rapport['anomalies_total'] ?? 0);
                $resultats['budget'] = [
                    'module' => 'budget',
                    'anomalies_total' => $total,
                    'anomalies_critiques' => $total > 0 ? $total : 0,
                    'score' => max(0, 100 - min(100, $total * 2)),
                ];
                if ($total > 0) {
                    $anomalies[] = [
                        'module' => 'budget',
                        'niveau' => $total >= 5 ? 'critique' : 'warning',
                        'code' => 'BUDGET_ANOMALIES',
                        'message' => "{$total} anomalie(s) budget / nomenclature / crédits.",
                    ];
                }
            }

            if (in_array('compteurs', $modules, true)) {
                $rapport = $this->compteurs->analyser($exerciceId);
                $total = (int) ($rapport['total_anomalies'] ?? 0);
                $resultats['compteurs'] = [
                    'module' => 'compteurs',
                    'anomalies_total' => $total,
                    'anomalies_critiques' => count($rapport['ecarts_compteurs'] ?? []),
                    'score' => max(0, 100 - min(100, $total * 3)),
                ];
                if ($total > 0) {
                    $anomalies[] = [
                        'module' => 'compteurs',
                        'niveau' => 'critique',
                        'code' => 'COMPTEURS_ECART',
                        'message' => "{$total} écart(s) sur les compteurs budgétaires.",
                    ];
                }
            }
        }

        if (in_array('rbm', $modules, true)) {
            $rapport = $this->rbm->executer($exerciceId);
            $resultats['rbm'] = $this->resumeModule('rbm', $rapport['anomalies'] ?? [], $rapport);
            $anomalies = array_merge($anomalies, $this->etiqueter($rapport['anomalies'] ?? [], 'rbm'));
        }

        if (in_array('depense', $modules, true)) {
            $rapport = $this->depense->executer($exerciceId, null, false);
            $resultats['depense'] = [
                'module' => 'depense',
                'anomalies_total' => (int) ($rapport['anomalies_total'] ?? 0),
                'anomalies_critiques' => (int) collect($rapport['anomalies'] ?? [])->where('gravite', 'elevee')->count(),
                'score' => (int) ($rapport['score_conformite'] ?? 0),
            ];
            $anomalies = array_merge($anomalies, $this->etiqueterDepenseSuivi($rapport['anomalies'] ?? [], 'depense'));
        }

        if (in_array('suivi', $modules, true)) {
            $rapport = $this->suivi->executer($exerciceId, null, false);
            $resultats['suivi'] = [
                'module' => 'suivi',
                'anomalies_total' => (int) ($rapport['anomalies_total'] ?? 0),
                'anomalies_critiques' => (int) collect($rapport['anomalies'] ?? [])->where('gravite', 'elevee')->count(),
                'score' => (int) ($rapport['score_conformite'] ?? 0),
            ];
            $anomalies = array_merge($anomalies, $this->etiqueterDepenseSuivi($rapport['anomalies'] ?? [], 'suivi'));
        }

        $critiques = collect($anomalies)->where('niveau', 'critique')->count();
        $score = $this->calculerScore($anomalies);

        $rapport = [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'modules_executes' => array_keys($resultats),
            'modules' => $resultats,
            'synthese' => [
                'anomalies_total' => count($anomalies),
                'anomalies_critiques' => $critiques,
                'score_conformite' => $score,
                'modules_en_echec' => collect($resultats)
                    ->filter(fn (array $m) => ($m['anomalies_total'] ?? 0) > 0)
                    ->keys()
                    ->values()
                    ->all(),
            ],
            'anomalies' => $anomalies,
        ];

        if ($persister) {
            AuditIntegriteData::create([
                'exercice_id' => $exerciceId,
                'score_conformite' => $score,
                'anomalies_total' => count($anomalies),
                'anomalies_critiques' => $critiques,
                'modules' => array_keys($resultats),
                'snapshot' => $rapport,
                'audite_le' => now(),
            ]);
        }

        return $rapport;
    }

    /** @param  list<array<string, mixed>>  $anomalies */
    public function depasseSeuil(array $anomalies, string $seuil = 'warning'): bool
    {
        $ordre = ['info' => 1, 'warning' => 2, 'critique' => 3];
        $min = $ordre[$seuil] ?? 2;

        foreach ($anomalies as $a) {
            $n = $a['niveau'] ?? 'warning';
            if (($ordre[$n] ?? 2) >= $min) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function modulesParDefaut(): array
    {
        return config('audit.integrity.modules', [
            'transversal', 'organisation', 'pap', 'budget', 'rbm', 'depense', 'suivi', 'compteurs',
        ]);
    }

    /** @param  list<string>|null  $modules */
    private function resoudreModules(?array $modules): array
    {
        $modules = $modules !== null && $modules !== [] ? $modules : $this->modulesParDefaut();

        return array_values(array_unique($modules));
    }

    /** @return list<array<string, string>> */
    private function auditerTransversal(?int $exerciceId): array
    {
        $anomalies = [];

        $orphelins = User::query()
            ->whereNotNull('structure_id')
            ->whereNotIn('structure_id', Structure::query()->select('id'))
            ->count();
        if ($orphelins > 0) {
            $anomalies[] = [
                'niveau' => 'warning',
                'code' => 'USER_ORPHELIN',
                'message' => "{$orphelins} utilisateur(s) rattaché(s) à une structure inexistante.",
            ];
        }

        $ouverts = Exercice::where('statut', 'ouvert')->count();
        if ($ouverts > 1) {
            $anomalies[] = [
                'niveau' => 'warning',
                'code' => 'EXERCICES_MULTIPLES_OUVERTS',
                'message' => "{$ouverts} exercices budgétaires ouverts simultanément.",
            ];
        }

        if ($exerciceId && Pap::where('exercice_id', $exerciceId)->count() > 1) {
            $anomalies[] = [
                'niveau' => 'info',
                'code' => 'PAP_MULTIPLES',
                'message' => 'Plusieurs PAP enregistrés pour le même exercice.',
            ];
        }

        if (Schema::hasTable('workflow_instances')) {
            $circuitsOrphelins = WorkflowInstance::query()
                ->where('statut', 'en_cours')
                ->get()
                ->filter(fn (WorkflowInstance $i) => $i->objet === null)
                ->count();
            if ($circuitsOrphelins > 0) {
                $anomalies[] = [
                    'niveau' => 'critique',
                    'code' => 'WORKFLOW_ORPHELIN',
                    'message' => "{$circuitsOrphelins} circuit(s) workflow en cours sans objet métier.",
                ];
            }
        }

        return $anomalies;
    }

    /** @param  list<array<string, mixed>>  $items */
    private function resumeModule(string $module, array $items, ?array $rapport = null): array
    {
        $critiques = collect($items)->where('niveau', 'critique')->count();

        return [
            'module' => $module,
            'anomalies_total' => count($items),
            'anomalies_critiques' => $critiques,
            'score' => max(0, 100 - min(100, collect($items)->sum(
                fn (array $a) => self::POIDS_NIVEAU[$a['niveau'] ?? 'warning'] ?? 5
            ))),
            'detail' => $rapport,
        ];
    }

    /** @param  list<array<string, mixed>>  $items */
    private function etiqueter(array $items, string $module): array
    {
        return collect($items)->map(fn (array $a) => [
            'module' => $module,
            'niveau' => $a['niveau'] ?? 'warning',
            'code' => $a['code'] ?? ($a['type'] ?? 'ANOMALIE'),
            'message' => $a['message'] ?? ($a['reference'] ?? $a['numero'] ?? 'Anomalie détectée'),
        ])->all();
    }

    /** @param  list<array<string, mixed>>  $items */
    private function etiqueterDepenseSuivi(array $items, string $module): array
    {
        return collect($items)->map(function (array $a) use ($module) {
            $gravite = $a['gravite'] ?? 'moyenne';

            return [
                'module' => $module,
                'niveau' => match ($gravite) {
                    'elevee' => 'critique',
                    'faible' => 'info',
                    default => 'warning',
                },
                'code' => $a['type'] ?? 'ANOMALIE',
                'message' => trim(($a['numero'] ?? $a['reference'] ?? '').' — '.($a['type'] ?? '')),
            ];
        })->all();
    }

    /** @param  list<array<string, mixed>>  $anomalies */
    private function calculerScore(array $anomalies): int
    {
        $penalite = collect($anomalies)->sum(fn (array $a) => self::POIDS_NIVEAU[$a['niveau'] ?? 'warning'] ?? 5);

        return max(0, 100 - min(100, $penalite));
    }
}
