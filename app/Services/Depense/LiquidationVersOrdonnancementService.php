<?php

namespace App\Services\Depense;

use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;

class LiquidationVersOrdonnancementService
{
    public function __construct(private readonly SeuilOrdonnancementService $seuils) {}

    public function assurerDepuisLiquidationValidee(Liquidation $liquidation): Ordonnancement
    {
        $ord = DB::transaction(function () use ($liquidation) {
            $verrouillee = Liquidation::query()->whereKey($liquidation->id)->lockForUpdate()->firstOrFail();

            $existant = Ordonnancement::query()
                ->where('liquidation_id', $verrouillee->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if ($existant) {
                return $existant;
            }

            $engagement = $verrouillee->engagement;
            $annee = $engagement?->exercice?->annee ?? (int) date('Y');

            $cree = Ordonnancement::create([
                'numero' => Numero::suivant('ordonnancements', 'ORD', $annee),
                'liquidation_id' => $verrouillee->id,
                'montant' => $verrouillee->montant,
                'statut' => 'brouillon',
                'cree_par' => $verrouillee->cree_par,
            ]);

            $this->seuils->appliquer($cree);

            return $cree;
        });

        app(FractionnementOrdonnancementService::class)->journaliser($ord);

        return $ord;
    }
}
