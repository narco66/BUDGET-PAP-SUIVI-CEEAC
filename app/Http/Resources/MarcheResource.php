<?php

namespace App\Http\Resources;

use App\Models\Marche;
use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarcheResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'objet' => $this->objet,
            'type_marche' => $this->type_marche,
            'type_libelle' => Marche::TYPES[$this->type_marche] ?? $this->type_marche,
            'mode_passation' => $this->mode_passation,
            'mode_libelle' => Marche::MODES[$this->mode_passation] ?? $this->mode_passation,
            'statut' => $this->statut,
            'statut_libelle' => Marche::STATUTS[$this->statut] ?? $this->statut,
            'montant_estime' => Montant::api($this->montant_estime),
            'montant_attribue' => Montant::api($this->montant_attribue),
            'attributaire' => $this->attributaire,
            'date_lancement' => $this->date_lancement?->toDateString(),
            'date_attribution' => $this->date_attribution?->toDateString(),
            'observations' => $this->observations,
            'exercice_id' => $this->exercice_id,
            'structure_id' => $this->structure_id,
            'expression_besoin_id' => $this->expression_besoin_id,
            'engagement_id' => $this->engagement_id,
            'exercice' => $this->whenLoaded('exercice', fn () => $this->exercice?->only(['id', 'annee', 'libelle'])),
            'structure' => $this->whenLoaded('structure', fn () => $this->structure?->only(['id', 'code', 'sigle', 'libelle'])),
            'besoin' => $this->whenLoaded('expressionBesoin', fn () => $this->expressionBesoin?->only(['id', 'numero'])),
            'engagement' => $this->whenLoaded('engagement', fn () => $this->engagement?->only(['id', 'numero', 'objet'])),
            'contrat' => $this->whenLoaded('contrat', fn () => $this->contrat ? [
                'id' => $this->contrat->id,
                'numero' => $this->contrat->numero,
            ] : null),
            'actions' => [
                'modifier' => (bool) $user?->can('marche.modifier'),
                'supprimer' => (bool) $user?->can('marche.supprimer'),
            ],
        ];
    }
}
