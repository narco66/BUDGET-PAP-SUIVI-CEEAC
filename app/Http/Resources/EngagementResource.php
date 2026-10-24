<?php

namespace App\Http\Resources;

use App\Support\Montant;
use App\Support\WorkflowResume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'type' => $this->type,
            'objet' => $this->objet,
            'beneficiaire' => $this->beneficiaire,
            'montant_total' => Montant::api($this->montant_total),
            'restant_a_liquider' => Montant::api($this->montantRestantLiquidation()),
            'chaine_cloturee_le' => $this->chaine_cloturee_le?->toIso8601String(),
            'chaine_soldee' => app(\App\Services\Depense\ChaineClotureService::class)->etat($this->resource)['soldee'],
            'statut' => $this->statut,
            'credits_reserves' => (bool) $this->credits_reserves,
            'diagnostic_credits' => $this->diagnostic_credits,
            'date_engagement' => $this->date_engagement?->toDateString(),
            'exercice_id' => $this->exercice_id,
            'expression_besoin_id' => $this->expression_besoin_id,
            'liquidation_id' => $this->whenLoaded('liquidations', fn () => $this->liquidations->first()?->id),
            'liquidation_numero' => $this->whenLoaded('liquidations', fn () => $this->liquidations->first()?->numero),
            'version' => $this->updated_at?->toIso8601String(),
            'verification_url' => '/api/v1/verification/engagement/'.$this->id,
            'imputations' => $this->whenLoaded('imputations', fn () => $this->imputations->map(fn ($i) => [
                'ligne' => $i->ligneBudgetaire?->code,
                'ligne_budgetaire_id' => $i->ligne_budgetaire_id,
                'montant' => Montant::api($i->montant),
            ])),
            'workflow' => WorkflowResume::api($this->resource, $user),
            'actions' => $user ? [
                'soumettre' => $user->can('update', $this->resource),
                'agir' => $user->can('agir', $this->resource),
                'liquider' => $user->can('liquidation.creer') && $this->statut === 'vise' && $this->montantRestantLiquidation() > 0.005,
                'degager' => $user->can('annuler', $this->resource) && in_array($this->statut, ['vise', 'soumis'], true),
            ] : [
                'soumettre' => false,
                'agir' => false,
                'liquider' => false,
                'degager' => false,
            ],
        ];
    }
}
