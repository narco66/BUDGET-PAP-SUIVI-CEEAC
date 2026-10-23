<?php

namespace App\Http\Resources;

use App\Models\Delegation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Délégation ou intérim actif exposé via l'API institutionnelle (phase A9). */
class GouvernanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'structure_code' => $this->structure?->code,
            'structure_libelle' => $this->structure?->libelle,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'motif' => $this->motif,
        ];

        if ($this->resource instanceof Delegation) {
            return [
                ...$base,
                'type' => 'delegation',
                'delegue_par' => $this->refUser($this->deleguant),
                'delegue_a' => $this->refUser($this->delegue),
            ];
        }

        return [
            ...$base,
            'type' => 'interim',
            'titulaire' => $this->refUser($this->titulaire),
            'interim' => $this->refUser($this->interim),
        ];
    }

    private function refUser(?\App\Models\User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'nom' => $user->name,
            'email' => $user->email,
        ];
    }
}
