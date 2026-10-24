<?php

namespace App\Http\Resources;

use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampagneSuiviResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'type' => $this->type,
            'periode' => $this->periode,
            'statut' => $this->statut,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'exercice_id' => $this->exercice_id,
            'exercice' => $this->whenLoaded('exercice', fn () => $this->exercice?->only(['id', 'annee', 'libelle'])),
            'collectes_count' => $this->whenCounted('collectes'),
            'actions' => [
                'saisir' => (bool) $user?->can('suivi.creer') && $this->estOuverte(),
                'cloturer' => (bool) $user?->can('suivi.valider') && $this->estOuverte(),
                'supprimer' => (bool) $user?->can('suivi.valider') && $this->estOuverte(),
            ],
            'consolidation' => $this->when(isset($this->resource->consolidation_api), fn () => collect($this->resource->consolidation_api)->map(fn ($ligne) => [
                'libelle' => $ligne->libelle,
                'n' => (int) $ligne->n,
                'phys' => Montant::api($ligne->phys, 1),
                'fin' => Montant::api($ligne->fin, 1),
            ])),
        ];
    }
}
