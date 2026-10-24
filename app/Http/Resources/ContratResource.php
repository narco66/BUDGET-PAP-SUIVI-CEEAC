<?php

namespace App\Http\Resources;

use App\Models\Contrat;
use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'objet' => $this->objet,
            'titulaire' => $this->titulaire,
            'type_contrat' => $this->type_contrat,
            'type_libelle' => Contrat::TYPES[$this->type_contrat] ?? $this->type_contrat,
            'statut' => $this->statut,
            'statut_libelle' => Contrat::STATUTS[$this->statut] ?? $this->statut,
            'montant' => Montant::api($this->montant),
            'date_signature' => $this->date_signature?->toDateString(),
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'observations' => $this->observations,
            'exercice_id' => $this->exercice_id,
            'marche_id' => $this->marche_id,
            'engagement_id' => $this->engagement_id,
            'structure_id' => $this->structure_id,
            'marche' => $this->whenLoaded('marche', fn () => $this->marche?->only(['id', 'numero'])),
            'engagement' => $this->whenLoaded('engagement', fn () => $this->engagement?->only(['id', 'numero', 'objet'])),
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->only(['id', 'code', 'sigle', 'libelle'])),
            'actions' => [
                'modifier' => (bool) $user?->can('contrat.modifier'),
                'supprimer' => (bool) $user?->can('contrat.supprimer'),
            ],
        ];
    }
}
