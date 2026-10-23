<?php

namespace App\Services\Budget;

use App\Models\Exercice;
use App\Models\SituationBudgetaire;
use App\Services\TableauBordService;
use Illuminate\Support\Facades\DB;

/** Capture et persistance des situations budgétaires consolidées (phase P1). */
class SituationBudgetaireService
{
    public function __construct(private readonly TableauBordService $tableauBord)
    {
    }

    /** @return array<string,mixed> */
    public function calculLive(int $exerciceId): array
    {
        return $this->tableauBord->executionBudgetaire($exerciceId);
    }

    public function capturer(int $exerciceId, ?int $userId = null): SituationBudgetaire
    {
        $live = $this->calculLive($exerciceId);
        $date = now()->toDateString();
        $attrs = [
            'budget_initial' => $live['ouvert'],
            'budget_revise' => $live['alloue'],
            'montant_engage' => $live['engage'],
            'montant_liquide' => $live['liquide'],
            'montant_ordonnance' => $live['ordonnance'],
            'montant_paye' => $live['paye'],
            'credit_disponible' => $live['disponible'],
            'reste_a_engager' => $live['reste_a_engager'],
            'reste_a_payer' => $live['reste_a_payer'],
            'taux_engagement' => $live['taux_engagement'],
            'taux_liquidation' => $live['taux_liquidation'],
            'taux_ordonnancement' => $live['taux_ordonnancement'],
            'taux_paiement' => $live['taux_paiement'],
            'taux_execution' => $live['taux_execution'],
            'genere_par' => $userId,
            'created_at' => now(),
        ];

        $situation = SituationBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->whereDate('date_situation', $date)
            ->first();

        if ($situation) {
            $situation->update($attrs);

            return $situation->fresh();
        }

        return SituationBudgetaire::create(array_merge($attrs, [
            'exercice_id' => $exerciceId,
            'date_situation' => $date,
        ]));
    }

    /** Lignes budgétaires avec reste à engager > 0. */
    public function restesAEngager(int $exerciceId): \Illuminate\Support\Collection
    {
        return DB::table('lignes_budgetaires as lb')
            ->leftJoin('structures as s', 'lb.structure_id', '=', 's.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->orderBy('lb.code')
            ->get([
                'lb.code', 'lb.intitule', 'lb.montant_ouvert', 'lb.montant_revise',
                'lb.montant_engage', 's.sigle', 's.libelle as structure_libelle',
            ])
            ->map(function ($lb) {
                $alloue = (float) ($lb->montant_revise ?? $lb->montant_ouvert);
                $engage = (float) $lb->montant_engage;
                $reste = max(0, $alloue - $engage);

                return [
                    'code' => $lb->code,
                    'intitule' => $lb->intitule,
                    'structure' => $lb->sigle ?? $lb->structure_libelle ?? '—',
                    'alloue' => $alloue,
                    'engage' => $engage,
                    'reste_a_engager' => $reste,
                    'taux_engagement' => SuiviFinancierCalculator::taux($engage, $alloue),
                ];
            })
            ->filter(fn ($r) => $r['reste_a_engager'] > 0 || $r['alloue'] > 0)
            ->values();
    }

    public function resoudreExerciceId(?string $opt): ?int
    {
        if (! $opt) {
            return ExerciceService::courant()?->id;
        }

        if (is_numeric($opt)) {
            $ex = Exercice::find((int) $opt) ?? Exercice::firstWhere('annee', (int) $opt);

            return $ex?->id;
        }

        return null;
    }

    /** Historique des snapshots persistés pour un exercice. */
    public function historique(int $exerciceId, int $limit = 30): \Illuminate\Support\Collection
    {
        return SituationBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->orderByDesc('date_situation')
            ->limit($limit)
            ->get([
                'id', 'exercice_id', 'date_situation',
                'budget_initial', 'budget_revise',
                'montant_engage', 'montant_liquide', 'montant_ordonnance', 'montant_paye',
                'credit_disponible', 'reste_a_engager', 'reste_a_payer',
                'taux_engagement', 'taux_liquidation', 'taux_ordonnancement', 'taux_paiement', 'taux_execution',
                'genere_par', 'created_at',
            ]);
    }
}
