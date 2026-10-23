<?php

namespace App\Services\Depense;

use App\Models\Paiement;
use App\Models\PaiementDetail;
use App\Services\Depense\DepenseCompteurService;

/** Ventilation multi-lignes d'un paiement (phase D3). */
class PaiementDetailService
{
    public function genererDepuisEngagement(Paiement $paiement): void
    {
        if ($paiement->details()->exists()) {
            return;
        }

        $engagement = $paiement->engagementRacine();
        $imputations = $engagement->imputations()->get();
        if ($imputations->isEmpty()) {
            return;
        }

        $montantPaye = (float) $paiement->montant;
        $parts = app(DepenseCompteurService::class)->repartir($montantPaye, $imputations);

        foreach ($parts as $ligneId => $part) {
            PaiementDetail::create([
                'paiement_id' => $paiement->id,
                'ligne_budgetaire_id' => $ligneId,
                'beneficiaire' => $engagement->beneficiaire,
                'reference' => $paiement->reference_bancaire,
                'montant' => $part,
            ]);
        }
    }
}
