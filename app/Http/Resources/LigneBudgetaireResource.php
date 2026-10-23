<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneBudgetaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'intitule' => $this->intitule,
            'rubrique' => $this->whenLoaded('rubrique', fn () => $this->rubrique?->code),
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->sigle),
            'montant_ouvert' => (float) $this->montant_ouvert,
            'montant_engage' => (float) $this->montant_engage,
            'montant_liquide' => (float) $this->montant_liquide,
            'montant_ordonnance' => (float) $this->montant_ordonnance,
            'montant_paye' => (float) $this->montant_paye,
            'disponible' => $this->disponible,
            'taux_consommation' => $this->taux_consommation,
            'statut_validation' => $this->statut_validation,
            'soumis_le' => $this->soumis_le?->toIso8601String(),
            'valide_le' => $this->valide_le?->toIso8601String(),
            'valide_par' => $this->whenLoaded('validePar', fn () => $this->validePar?->only(['id', 'name'])),
            'motif_rejet' => $this->motif_rejet,
            'exercice_id' => $this->exercice_id,
            'structure_id' => $this->structure_id,
            'activite_id' => $this->activite_id,
        ];
    }
}
