<?php

namespace App\Services\Depense;

use App\Models\Paiement;

/** Conversion multi-devises des paiements (phase D4). */
class DevisePaiementService
{
    public const DEVISES = ['XAF' => 'Franc CFA (XAF)', 'EUR' => 'Euro (EUR)', 'USD' => 'Dollar (USD)'];

    /** @param array<string,mixed> $data */
    public function appliquer(Paiement $paiement, array $data): Paiement
    {
        $devise = strtoupper($data['devise'] ?? $paiement->devise ?? 'XAF');
        $taux = (float) ($data['taux_change'] ?? $paiement->taux_change ?? 1);
        if ($devise === 'XAF') {
            $taux = 1;
        }
        if ($taux <= 0) {
            $taux = 1;
        }

        $montant = (float) ($data['montant'] ?? $paiement->montant);
        $montantXaf = round($montant * $taux, 2);

        $paiement->update([
            'devise' => $devise,
            'taux_change' => $taux,
            'montant_xaf' => $montantXaf,
        ]);

        return $paiement->fresh();
    }

    public function montantXaf(Paiement $paiement): float
    {
        return (float) ($paiement->montant_xaf ?? $paiement->montant);
    }
}
