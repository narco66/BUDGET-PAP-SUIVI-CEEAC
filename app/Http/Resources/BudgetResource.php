<?php

namespace App\Http\Resources;

use App\Support\Montant;
use App\Support\WorkflowResume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'libelle' => $this->libelle,
            'statut' => $this->statut,
            'version' => (int) $this->version,
            'etag' => $this->updated_at?->toIso8601String(),
            'observations' => $this->observations,
            'motif_rejet' => $this->motif_rejet,
            'date_elaboration' => $this->date_elaboration?->toDateString(),
            'adopte_le' => $this->adopte_le?->toIso8601String(),
            'montant' => Montant::api($this->montant_total),
            'montant_total' => (float) $this->montant_total,
            'exercice_id' => $this->exercice_id,
            'exercice' => $this->whenLoaded('exercice', fn () => $this->exercice?->only(['id', 'annee', 'libelle', 'statut'])),
            'structure_id' => $this->structure_id,
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->only(['id', 'code', 'libelle'])),
            'responsable_id' => $this->responsable_id,
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable?->only(['id', 'name', 'email'])),
            'workflow' => WorkflowResume::api($this->resource, $user),
            'actions' => $user ? [
                'modifier' => $user->can('update', $this->resource),
                'soumettre' => $user->can('soumettre', $this->resource),
                'agir' => $user->can('agir', $this->resource),
                'reviser' => $user->can('reviser', $this->resource),
                'preparer' => $user->can('preparer', $this->resource),
                'simuler' => $user->can('simuler', $this->resource),
                'rectifier' => $user->can('rectifier', $this->resource),
            ] : [
                'modifier' => false,
                'soumettre' => false,
                'agir' => false,
                'reviser' => false,
                'preparer' => false,
                'simuler' => false,
                'rectifier' => false,
            ],
        ];
    }
}
