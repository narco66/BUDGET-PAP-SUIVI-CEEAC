<?php

namespace App\Http\Resources;

use App\Services\Institution\InstitutionMetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Structure institutionnelle avec lineage departement / direction / service / responsable (phase A9). */
class StructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lineage = app(InstitutionMetricsService::class)->lineage($this->resource);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'sigle' => $this->sigle,
            'type' => $this->type,
            'parent_id' => $this->parent_id,
            'parent_code' => $this->parent?->code,
            'niveau' => $this->niveau,
            'ordre' => $this->ordre,
            'actif' => $this->actif,
            'structure_officielle_code' => $this->structureOfficielle?->code,
            'departement' => $this->refLineage($lineage['departement']),
            'direction' => $this->refLineage($lineage['direction']),
            'service' => $this->refLineage($lineage['service']),
            'responsable' => $lineage['responsable']
                ? [
                    'id' => $lineage['responsable']->id,
                    'nom' => $lineage['responsable']->name,
                    'email' => $lineage['responsable']->email,
                ]
                : null,
        ];
    }

    /** @param  \App\Models\Structure|null  $structure */
    private function refLineage($structure): ?array
    {
        if (! $structure) {
            return null;
        }

        return [
            'code' => $structure->code,
            'libelle' => $structure->libelle,
        ];
    }
}
