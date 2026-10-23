<?php

namespace App\Services\Depense;

use App\Models\EngagementImputation;
use Illuminate\Support\Collection;

/** Répartition prorata des montants aval sur les imputations d'engagement (source unique). */
class DepenseCompteurService
{
    /**
     * @param  Collection<int,EngagementImputation>|array<int,EngagementImputation>  $imputations
     * @return array<int,float> ligne_budgetaire_id => montant
     */
    public function repartir(float $montantEtape, Collection|array $imputations): array
    {
        $imputations = $imputations instanceof Collection ? $imputations->values() : collect(array_values($imputations));
        $totalEngagement = (float) $imputations->sum('montant');

        if ($totalEngagement <= 0 || $montantEtape <= 0 || $imputations->isEmpty()) {
            return [];
        }

        $parts = [];
        $cumul = 0.0;
        $dernier = $imputations->count() - 1;

        foreach ($imputations as $i => $imp) {
            $part = $i === $dernier
                ? round($montantEtape - $cumul, 2)
                : round($montantEtape * ((float) $imp->montant / $totalEngagement), 2);
            $cumul += $part;

            $ligneId = (int) $imp->ligne_budgetaire_id;
            $parts[$ligneId] = ($parts[$ligneId] ?? 0) + $part;
        }

        return $parts;
    }

    /** Fusionne des répartitions partielles par ligne. */
    public function fusionner(array $base, array $ajout): array
    {
        foreach ($ajout as $ligneId => $montant) {
            $base[$ligneId] = ($base[$ligneId] ?? 0) + $montant;
        }

        return $base;
    }
}
