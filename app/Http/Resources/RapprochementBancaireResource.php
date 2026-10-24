<?php

namespace App\Http\Resources;

use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RapprochementBancaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $ouvert = in_array($this->statut, ['brouillon', 'en_cours'], true);
        $ecriture = (bool) ($user?->can('depenses.admin') || $user?->can('paiement.valider'));

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'intitule' => $this->intitule,
            'compte_bancaire' => $this->compte_bancaire,
            'exercice_id' => $this->exercice_id,
            'exercice' => $this->whenLoaded('exercice', fn () => $this->exercice?->libelle ?: (string) $this->exercice?->annee),
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'montant_releve' => Montant::api($this->montant_releve),
            'montant_rapproche' => Montant::api($this->montant_rapproche),
            'statut' => $this->statut,
            'cloture_le' => $this->cloture_le?->toIso8601String(),
            'version' => $this->updated_at?->toIso8601String(),
            'lignes' => $this->whenLoaded('lignes', fn () => $this->lignes->map(fn ($l) => [
                'id' => $l->id,
                'date_operation' => $l->date_operation?->toDateString(),
                'libelle' => $l->libelle,
                'reference_bancaire' => $l->reference_bancaire,
                'montant' => Montant::api($l->montant),
                'statut' => $l->statut,
                'paiement_id' => $l->paiement_id,
                'paiement' => $l->paiement?->numero,
            ])),
            'actions' => $user ? [
                'importer' => $ouvert && $ecriture,
                'auto' => $ouvert && $ecriture,
                'lier' => $ouvert && $ecriture,
                'cloturer' => $ouvert && $ecriture,
            ] : [
                'importer' => false,
                'auto' => false,
                'lier' => false,
                'cloturer' => false,
            ],
        ];
    }
}
