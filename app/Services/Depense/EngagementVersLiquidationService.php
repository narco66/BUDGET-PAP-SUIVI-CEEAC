<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\Liquidation;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;

class EngagementVersLiquidationService
{
    public function assurerDepuisEngagementVise(Engagement $engagement): Liquidation
    {
        return DB::transaction(function () use ($engagement) {
            $verrouille = Engagement::query()->whereKey($engagement->id)->lockForUpdate()->firstOrFail();

            $existante = Liquidation::query()
                ->where('engagement_id', $verrouille->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if ($existante) {
                return $existante;
            }

            $annee = $verrouille->exercice?->annee ?? (int) date('Y');

            return Liquidation::create([
                'numero' => Numero::suivant('liquidations', 'LIQ', $annee),
                'engagement_id' => $verrouille->id,
                'montant' => $verrouille->montant_total,
                'numero_tranche' => 1,
                'statut' => 'brouillon',
                'cree_par' => $verrouille->cree_par,
            ]);
        });
    }
}
