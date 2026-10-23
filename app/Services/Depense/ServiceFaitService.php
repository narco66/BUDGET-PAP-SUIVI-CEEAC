<?php



namespace App\Services\Depense;



use App\Models\Liquidation;

use App\Models\LiquidationPiece;

use App\Models\ServiceFait;

use App\Models\User;



/** Enregistrement structuré du service fait et typologie pièces liquidation (phase D2). */

class ServiceFaitService

{

    /** @param array<string,mixed> $data */

    public function enregistrer(Liquidation $liquidation, array $data, User $user): ServiceFait

    {

        $serviceFait = $liquidation->servicesFaits()->create([

            'type' => $data['service_fait_type'] ?? 'constat',

            'reference' => $data['service_fait_reference'] ?? null,

            'date_constat' => $data['service_fait_date'] ?? $liquidation->date_liquidation ?? now()->toDateString(),

            'constatation' => $data['service_fait'] ?? $data['constatation'] ?? null,

            'responsable_id' => $user->id,

        ]);



        if (! empty($data['service_fait'])) {

            $liquidation->update(['service_fait' => $data['service_fait']]);

        }



        if (! empty($data['piece_type'])) {

            $liquidation->piecesLiquidation()->create([

                'type' => $data['piece_type'],

                'reference' => $data['piece_reference'] ?? null,

                'date_piece' => $data['piece_date'] ?? null,

            ]);

        }



        return $serviceFait;

    }

}


