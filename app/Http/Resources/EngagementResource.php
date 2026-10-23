<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'type' => $this->type,
            'objet' => $this->objet,
            'beneficiaire' => $this->beneficiaire,
            'montant_total' => (float) $this->montant_total,
            'statut' => $this->statut,
            'credits_reserves' => (bool) $this->credits_reserves,
            'date_engagement' => $this->date_engagement?->toDateString(),
            'imputations' => $this->whenLoaded('imputations', fn () => $this->imputations->map(fn ($i) => [
                'ligne' => $i->ligneBudgetaire?->code,
                'montant' => (float) $i->montant,
            ])),
        ];
    }
}
