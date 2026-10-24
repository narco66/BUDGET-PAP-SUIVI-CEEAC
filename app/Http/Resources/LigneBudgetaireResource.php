<?php

namespace App\Http\Resources;

use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneBudgetaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'intitule' => $this->intitule,
            'rubrique' => $this->whenLoaded('rubrique', fn () => $this->rubrique?->code),
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->sigle ?: $this->structure?->libelle),
            'montant_ouvert' => Montant::api($this->montant_ouvert),
            'montant_engage' => Montant::api($this->montant_engage),
            'montant_liquide' => Montant::api($this->montant_liquide),
            'montant_ordonnance' => Montant::api($this->montant_ordonnance),
            'montant_paye' => Montant::api($this->montant_paye),
            'disponible' => Montant::api($this->disponible),
            'taux_consommation' => Montant::api($this->taux_consommation),
            'statut_validation' => $this->statut_validation,
            'soumis_le' => $this->soumis_le?->toIso8601String(),
            'valide_le' => $this->valide_le?->toIso8601String(),
            'valide_par' => $this->whenLoaded('validePar', fn () => $this->validePar?->only(['id', 'name'])),
            'motif_rejet' => $this->motif_rejet,
            'exercice_id' => $this->exercice_id,
            'structure_id' => $this->structure_id,
            'activite_id' => $this->activite_id,
            'version' => $this->updated_at?->toIso8601String(),
            'actions' => $user ? [
                'soumettre' => $user->can('structureBudget.submitLigne', $this->resource),
                'valider' => $user->can('structureBudget.validateLigne', $this->resource),
                'rejeter' => $user->can('structureBudget.rejectLigne', $this->resource),
            ] : [
                'soumettre' => false,
                'valider' => false,
                'rejeter' => false,
            ],
        ];
    }
}
