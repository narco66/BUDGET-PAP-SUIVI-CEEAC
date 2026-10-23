<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\Liquidation;
use RuntimeException;

/** Contrôle des plafonds cumulés sur la chaîne de la dépense (liquidations, etc.). */
class DepenseQuotaService
{
    /** Statuts exclus du cumul (rejet ou annulation). */
    private const EXCLUS = ['rejete', 'annule'];

    /** Montant déjà couvert par des liquidations actives sur l'engagement. */
    public function montantLiquideEngagement(Engagement $engagement, ?int $ignoreLiquidationId = null): float
    {
        $q = $engagement->liquidations()->whereNotIn('statut', self::EXCLUS);
        if ($ignoreLiquidationId) {
            $q->where('id', '!=', $ignoreLiquidationId);
        }

        return (float) $q->sum('montant');
    }

    public function montantRestantLiquidation(Engagement $engagement, ?int $ignoreLiquidationId = null): float
    {
        return max(0, round((float) $engagement->montant_total - $this->montantLiquideEngagement($engagement, $ignoreLiquidationId), 2));
    }

    /** @throws RuntimeException */
    public function assertPeutLiquider(Engagement $engagement, float $montant, ?int $ignoreLiquidationId = null): void
    {
        if ($montant <= 0) {
            throw new RuntimeException('Le montant de liquidation doit être strictement positif.');
        }

        $restant = $this->montantRestantLiquidation($engagement, $ignoreLiquidationId);
        if ($montant > $restant + 0.01) {
            throw new RuntimeException(
                'Montant de liquidation dépassé : reste '.number_format($restant, 0, ',', ' ').
                ' XAF sur l\'engagement '.$engagement->numero.'.'
            );
        }
    }

    /** Vérifie qu'une liquidation existante reste dans le plafond avant validation workflow. */
    public function assertLiquidationCoherente(Liquidation $liquidation): void
    {
        $this->assertPeutLiquider(
            $liquidation->engagement,
            (float) $liquidation->montant,
            $liquidation->id
        );
    }
}
