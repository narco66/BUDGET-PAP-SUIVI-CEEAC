<?php

namespace App\Services\Reporting;

use App\Models\Exercice;
use App\Services\Pap\PapPerformanceService;
use Illuminate\Support\Collection;

/** Rapports de performance stratégique pilier / axe / département (P2). */
class RapportStrategiqueService
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly PapPerformanceService $pap,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function sectionsPerformancePilier(Exercice $exercice): array
    {
        $piliers = $this->reporting->parPilier($exercice->id);

        return [
            $this->introStrategique('pilier', $exercice),
            $this->kpiStrategique($piliers),
            [
                'type' => 'table',
                'titre' => 'Performance par pilier stratégique',
                'colonnes' => $this->colonnesStrategiques(true),
                'lignes' => $piliers->map(fn ($r) => [
                    $r['rang'],
                    $r['code'],
                    $r['libelle'],
                    $r['axes'],
                    $r['alloue'],
                    $r['paye'],
                    $r['taux_execution'],
                    $r['taux_physique'],
                    $r['indice_performance'],
                ])->all(),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function sectionsPerformanceAxe(Exercice $exercice): array
    {
        $axes = $this->reporting->parAxe($exercice->id)
            ->sortByDesc('taux_execution')
            ->values()
            ->map(fn ($r, $i) => array_merge($r, ['rang' => $i + 1]));

        return [
            $this->introStrategique('axe', $exercice),
            $this->kpiStrategique($axes),
            [
                'type' => 'table',
                'titre' => 'Performance par axe stratégique',
                'colonnes' => $this->colonnesStrategiques(true, true),
                'lignes' => $axes->map(fn ($r) => [
                    $r['rang'],
                    $r['pilier'],
                    $r['code'],
                    $r['intitule'],
                    $r['alloue'] ?? $r['ouvert'],
                    $r['paye'],
                    $r['taux_execution'],
                    $r['taux_engagement'] ?? 0,
                ])->all(),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function sectionsPerformanceDepartement(Exercice $exercice): array
    {
        $departements = $this->reporting->performanceDepartements($exercice->id);

        return [
            $this->introStrategique('departement', $exercice),
            [
                'type' => 'kpi',
                'titre' => 'Synthèse départements',
                'items' => [
                    ['label' => 'Départements suivis', 'valeur' => $departements->count(), 'fmt' => 'int'],
                    ['label' => 'Indice moyen', 'valeur' => round((float) $departements->avg('indice_performance'), 1), 'fmt' => 'percent'],
                    ['label' => 'Taux phys. moyen', 'valeur' => round((float) $departements->avg('taux_physique'), 1), 'fmt' => 'percent'],
                    ['label' => 'Taux fin. moyen', 'valeur' => round((float) $departements->avg('taux_financier'), 1), 'fmt' => 'percent'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Classement des départements',
                'colonnes' => [
                    ['label' => 'Rang', 'fmt' => 'int', 'align' => 'right'],
                    ['label' => 'Département', 'fmt' => 'text', 'align' => 'left'],
                    ['label' => 'Activités', 'fmt' => 'int', 'align' => 'right'],
                    ['label' => 'Taux phys.', 'fmt' => 'percent', 'align' => 'right'],
                    ['label' => 'Taux fin.', 'fmt' => 'percent', 'align' => 'right'],
                    ['label' => 'Indice perf.', 'fmt' => 'percent', 'align' => 'right'],
                    ['label' => 'Budget payé', 'fmt' => 'money', 'align' => 'right'],
                ],
                'lignes' => $departements->map(fn ($r) => [
                    $r['rang'],
                    $r['libelle'],
                    $r['activites'],
                    $r['taux_physique'],
                    $r['taux_financier'],
                    $r['indice_performance'],
                    $r['budget_paye'],
                ])->all(),
            ],
        ];
    }

    private function introStrategique(string $niveau, Exercice $exercice): array
    {
        $texte = match ($niveau) {
            'pilier' => "Analyse de performance consolidée par pilier stratégique — exercice {$exercice->annee}.",
            'axe' => "Analyse de performance par axe, avec classement et tendances d'exécution — exercice {$exercice->annee}.",
            default => "Score global, taux d'exécution et consommation budgétaire par département — exercice {$exercice->annee}.",
        };

        return ['type' => 'text', 'titre' => 'Contexte', 'contenu' => $texte];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function kpiStrategique(Collection $rows): array
    {
        return [
            'type' => 'kpi',
            'titre' => 'Indicateurs agrégés',
            'items' => [
                ['label' => 'Entités analysées', 'valeur' => $rows->count(), 'fmt' => 'int'],
                ['label' => "Taux exécution moyen", 'valeur' => round((float) $rows->avg('taux_execution'), 1), 'fmt' => 'percent'],
                ['label' => 'Montant payé total', 'valeur' => $rows->sum('paye'), 'fmt' => 'money'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function colonnesStrategiques(bool $withRang = false, bool $withPilier = false): array
    {
        $cols = [];
        if ($withRang) {
            $cols[] = ['label' => 'Rang', 'fmt' => 'int', 'align' => 'right'];
        }
        if ($withPilier) {
            $cols[] = ['label' => 'Pilier', 'fmt' => 'text', 'align' => 'left'];
        }
        $cols = array_merge($cols, [
            ['label' => 'Code', 'fmt' => 'text', 'align' => 'left'],
            ['label' => 'Libellé', 'fmt' => 'text', 'align' => 'left'],
        ]);
        if (! $withPilier) {
            array_splice($cols, count($cols), 0, [['label' => 'Axes', 'fmt' => 'int', 'align' => 'right']]);
        }
        $cols = array_merge($cols, [
            ['label' => 'Alloué', 'fmt' => 'money', 'align' => 'right'],
            ['label' => 'Payé', 'fmt' => 'money', 'align' => 'right'],
            ['label' => "Taux exéc.", 'fmt' => 'percent', 'align' => 'right'],
        ]);
        if (! $withPilier) {
            $cols[] = ['label' => 'Taux phys.', 'fmt' => 'percent', 'align' => 'right'];
            $cols[] = ['label' => 'Indice perf.', 'fmt' => 'percent', 'align' => 'right'];
        } else {
            $cols[] = ['label' => "Taux eng.", 'fmt' => 'percent', 'align' => 'right'];
        }

        return $cols;
    }
}
