<?php

namespace App\Http\Resources;

use App\Models\Formation;
use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'intitule' => $this->intitule,
            'type' => $this->type,
            'type_libelle' => Formation::TYPES[$this->type] ?? $this->type,
            'thematique' => $this->thematique,
            'lieu' => $this->lieu,
            'statut' => $this->statut,
            'statut_libelle' => Formation::STATUTS[$this->statut] ?? $this->statut,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'nb_participants' => (int) $this->nb_participants,
            'nb_hommes' => (int) $this->nb_hommes,
            'nb_femmes' => (int) $this->nb_femmes,
            'cout' => Montant::api($this->cout),
            'niveau_satisfaction' => $this->niveau_satisfaction !== null ? Montant::api($this->niveau_satisfaction) : null,
            'impact' => $this->impact,
            'observations' => $this->observations,
            'exercice_id' => $this->exercice_id,
            'structure_id' => $this->structure_id,
            'source_financement_id' => $this->source_financement_id,
            'engagement_id' => $this->engagement_id,
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->only(['id', 'code', 'sigle', 'libelle'])),
            'source_financement' => $this->whenLoaded('sourceFinancement', fn () => $this->sourceFinancement?->only(['id', 'code', 'libelle'])),
            'engagement' => $this->whenLoaded('engagement', fn () => $this->engagement?->only(['id', 'numero'])),
            'actions' => [
                'modifier' => (bool) $user?->can('formation.modifier'),
                'supprimer' => (bool) $user?->can('formation.supprimer'),
            ],
        ];
    }
}
