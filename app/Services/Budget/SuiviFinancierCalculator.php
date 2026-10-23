<?php

namespace App\Services\Budget;

/** Source unique des indicateurs de suivi financier (phase P0). */
final class SuiviFinancierCalculator
{
    /** Budget adopté = révisé si défini, sinon ouvert (aligné LigneBudgetaire::montant_alloue). */
    public static function budgetAdopte(float $ouvert, ?float $alloue = null): float
    {
        return $alloue ?? $ouvert;
    }

    public static function taux(float $numerateur, float $denominateur): float
    {
        return $denominateur > 0 ? round($numerateur / $denominateur * 100, 1) : 0.0;
    }

    /**
     * @return array{
     *   ouvert: float, alloue: float, revise_delta: float,
     *   engage: float, liquide: float, ordonnance: float, paye: float, decaisse: float,
     *   disponible: float, reste_a_engager: float, reste_a_payer: float,
     *   taux_engagement: float, taux_liquidation: float, taux_ordonnancement: float,
     *   taux_paiement: float, taux_execution: float
     * }
     */
    public static function synthese(
        float $ouvert,
        float $engage,
        float $liquide,
        float $ordonnance,
        float $paye,
        ?float $alloue = null,
    ): array {
        $alloue ??= $ouvert;

        return [
            'ouvert' => $ouvert,
            'alloue' => $alloue,
            'revise_delta' => $alloue - $ouvert,
            'engage' => $engage,
            'liquide' => $liquide,
            'ordonnance' => $ordonnance,
            'paye' => $paye,
            'decaisse' => $paye,
            'disponible' => $alloue - $engage,
            'reste_a_engager' => max(0, $alloue - $engage),
            'reste_a_payer' => max(0, $ordonnance - $paye),
            'taux_engagement' => self::taux($engage, $alloue),
            'taux_liquidation' => self::taux($liquide, $engage),
            'taux_ordonnancement' => self::taux($ordonnance, $liquide),
            'taux_paiement' => self::taux($paye, $ordonnance),
            'taux_execution' => self::taux($paye, $alloue),
        ];
    }
}
