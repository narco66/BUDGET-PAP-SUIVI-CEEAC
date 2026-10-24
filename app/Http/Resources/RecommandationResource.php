<?php

namespace App\Http\Resources;

use App\Models\Recommandation;
use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommandationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'libelle' => $this->libelle,
            'description' => $this->description,
            'source' => $this->source,
            'source_libelle' => Recommandation::SOURCES[$this->source] ?? $this->source,
            'categorie' => $this->categorie,
            'categorie_libelle' => $this->categorie ? (Recommandation::CATEGORIES[$this->categorie] ?? $this->categorie) : null,
            'priorite' => $this->priorite,
            'priorite_libelle' => Recommandation::PRIORITES[$this->priorite] ?? $this->priorite,
            'statut' => $this->statut,
            'statut_libelle' => Recommandation::STATUTS[$this->statut] ?? $this->statut,
            'taux_execution' => Montant::api($this->taux_execution),
            'date_emission' => $this->date_emission?->toDateString(),
            'echeance' => $this->echeance?->toDateString(),
            'reference_reunion' => $this->reference_reunion,
            'observations' => $this->observations,
            'exercice_id' => $this->exercice_id,
            'structure_id' => $this->structure_id,
            'responsable_user_id' => $this->responsable_user_id,
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->only(['id', 'code', 'sigle', 'libelle'])),
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable?->only(['id', 'name'])),
            'actions' => [
                'modifier' => (bool) $user?->can('recommandation.modifier'),
                'supprimer' => (bool) $user?->can('recommandation.supprimer'),
            ],
        ];
    }
}
