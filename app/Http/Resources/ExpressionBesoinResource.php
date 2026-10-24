<?php

namespace App\Http\Resources;

use App\Support\Montant;
use App\Support\WorkflowResume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressionBesoinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $engagement = $this->relationLoaded('engagement') ? $this->engagement : $this->engagement()->first();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'categorie' => $this->categorie ?? 'pap',
            'objet' => $this->objet,
            'justification' => $this->justification,
            'montant_estime' => Montant::api($this->montant_estime),
            'priorite' => $this->priorite,
            'statut' => $this->statut,
            'date_besoin' => $this->date_besoin?->toDateString(),
            'exercice_id' => $this->exercice_id,
            'structure_id' => $this->structure_id,
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->sigle ?: $this->structure?->libelle),
            'activite_id' => $this->activite_id,
            'activite' => $this->whenLoaded('activite', fn () => $this->activite?->code),
            'ligne_budgetaire_id' => $this->ligne_budgetaire_id,
            'ligne_budgetaire' => $this->whenLoaded('ligneBudgetaire', fn () => $this->ligneBudgetaire?->code),
            'demandeur' => $this->whenLoaded('demandeur', fn () => $this->demandeur?->name),
            'heritage_pap' => $this->heritage_pap,
            'alerte_credits' => $this->alerte_credits,
            'engagement_id' => $engagement?->id,
            'engagement_numero' => $engagement?->numero,
            'lignes' => $this->whenLoaded('lignes', fn () => $this->lignes->map(fn ($l) => [
                'id' => $l->id,
                'ordre' => $l->ordre,
                'libelle' => $l->libelle,
                'quantite' => (string) $l->quantite,
                'unite' => $l->unite,
                'prix_unitaire' => Montant::api($l->prix_unitaire),
                'taxe' => Montant::api($l->taxe),
                'montant' => Montant::api($l->montant),
            ])->values()),
            'version' => $this->updated_at?->toIso8601String(),
            'verification_url' => '/api/v1/verification/besoin/'.$this->id,
            'workflow' => WorkflowResume::api($this->resource, $user),
            'actions' => $user ? [
                'soumettre' => $user->can('update', $this->resource),
                'agir' => $user->can('agir', $this->resource),
                'modifier' => $user->can('update', $this->resource),
                'convertir' => $user->can('view', $this->resource)
                    && $user->can('engagement.creer')
                    && in_array($this->statut, ['valide', 'converti'], true)
                    && $engagement === null,
            ] : [
                'soumettre' => false,
                'agir' => false,
                'modifier' => false,
                'convertir' => false,
            ],
        ];
    }
}
