<?php

namespace App\Http\Resources;

use App\Models\Immobilisation;
use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImmobilisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'designation' => $this->designation,
            'categorie' => $this->categorie,
            'categorie_libelle' => Immobilisation::CATEGORIES[$this->categorie] ?? $this->categorie,
            'marque' => $this->marque,
            'numero_serie' => $this->numero_serie,
            'localisation' => $this->localisation,
            'affectation' => $this->affectation,
            'date_acquisition' => $this->date_acquisition?->toDateString(),
            'valeur_acquisition' => Montant::api($this->valeur_acquisition),
            'amortissement_cumule' => Montant::api($this->amortissementCumule()),
            'valeur_nette' => Montant::api($this->valeurNette()),
            'duree_amortissement_ans' => (int) $this->duree_amortissement_ans,
            'compte_syscohada' => $this->compte_syscohada,
            'statut' => $this->statut,
            'statut_libelle' => Immobilisation::STATUTS[$this->statut] ?? $this->statut,
            'observations' => $this->observations,
            'exercice_id' => $this->exercice_id,
            'structure_id' => $this->structure_id,
            'engagement_id' => $this->engagement_id,
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->only(['id', 'code', 'sigle', 'libelle'])),
            'engagement' => $this->whenLoaded('engagement', fn () => $this->engagement?->only(['id', 'numero'])),
            'actions' => [
                'modifier' => (bool) $user?->can('immobilisation.modifier'),
                'supprimer' => (bool) $user?->can('immobilisation.supprimer'),
            ],
        ];
    }
}
