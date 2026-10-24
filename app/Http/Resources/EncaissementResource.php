<?php

namespace App\Http\Resources;

use App\Support\Montant;
use App\Support\WorkflowResume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncaissementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'statut' => $this->statut,
            'montant' => Montant::api($this->montant),
            'montant_xaf' => Montant::api($this->montant_xaf),
            'devise' => $this->devise,
            'taux_change' => Montant::api($this->taux_change, 6),
            'mode_paiement' => $this->mode_paiement,
            'banque' => $this->banque,
            'reference_bancaire' => $this->reference_bancaire,
            'date_encaissement' => $this->date_encaissement?->toDateString(),
            'date_rapprochement' => $this->date_rapprochement?->toDateString(),
            'observations' => $this->observations,
            'motif_rejet' => $this->motif_rejet,
            'recette_id' => $this->recette_id,
            'recette' => $this->whenLoaded('recette', fn () => $this->recette?->reference),
            'justificatifs' => (int) ($this->pieces_count ?? ($this->relationLoaded('pieces') ? $this->pieces->count() : 0)),
            'etag' => $this->updated_at?->toIso8601String(),
            'workflow' => WorkflowResume::api($this->resource, $user),
            'actions' => $user ? [
                'soumettre' => $user->can('soumettre', $this->resource),
                'agir' => $user->can('agir', $this->resource),
                'rapprocher' => $user->can('rapprocher', $this->resource) && $this->statut === 'valide',
                'annuler' => $user->can('annuler', $this->resource),
            ] : [
                'soumettre' => false,
                'agir' => false,
                'rapprocher' => false,
                'annuler' => false,
            ],
        ];
    }
}
