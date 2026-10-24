<?php

namespace App\Http\Resources;

use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecetteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $prevu = (float) $this->montant_prevu;
        $realise = (float) $this->montant_realise;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'observations' => $this->observations,
            'statut' => $this->statut,
            'montant_prevu' => Montant::api($prevu),
            'montant_realise' => Montant::api($realise),
            'ecart' => Montant::api($realise - $prevu),
            'date_prevue' => $this->date_prevue?->toDateString(),
            'exercice_id' => $this->exercice_id,
            'exercice' => $this->whenLoaded('exercice', fn () => $this->exercice?->only(['id', 'annee', 'libelle'])),
            'categorie' => $this->whenLoaded('categorie', fn () => $this->categorie?->libelle),
            'etat_membre' => $this->whenLoaded('etatMembre', fn () => $this->etatMembre?->pays),
            'rubrique_id' => $this->rubrique_id,
            'rubrique' => $this->whenLoaded('rubrique', fn () => $this->rubrique?->code),
            'etag' => $this->updated_at?->toIso8601String(),
            'encaissements_count' => (int) ($this->encaissements_count
                ?? ($this->relationLoaded('encaissements') ? $this->encaissements->count() : 0)),
            'encaissements' => EncaissementResource::collection($this->whenLoaded('encaissements')),
            'actions' => $user ? [
                'ventiler' => $user->can('update', $this->resource),
                'encaisser' => $user->can('encaisser', $this->resource)
                    && in_array($this->statut, ['prevue', 'partiellement_realisee'], true),
            ] : [
                'ventiler' => false,
                'encaisser' => false,
            ],
        ];
    }
}
