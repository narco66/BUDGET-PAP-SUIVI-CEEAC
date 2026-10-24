<?php

namespace App\Http\Resources;

use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppelFondsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $appele = (float) $this->montant_appele;
        $regle = (float) $this->montant_regle;
        $planActif = $this->relationLoaded('plansApurement')
            ? $this->plansApurement->firstWhere('statut', 'actif')
            : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'objet' => $this->objet,
            'statut' => $this->statut,
            'montant_appele' => Montant::api($appele),
            'montant_regle' => Montant::api($regle),
            'solde' => Montant::api($this->solde),
            'penalite' => Montant::api($this->penalite),
            'taux_penalite' => Montant::api($this->taux_penalite, 1),
            'taux_paiement' => Montant::api($this->taux_paiement, 1),
            'en_retard' => $this->enRetard(),
            'date_emission' => $this->date_emission?->toDateString(),
            'date_echeance' => $this->date_echeance?->toDateString(),
            'observations' => $this->observations,
            'exercice_id' => $this->exercice_id,
            'exercice' => $this->whenLoaded('exercice', fn () => $this->exercice?->only(['id', 'annee', 'libelle'])),
            'etat_membre' => $this->whenLoaded('etatMembre', fn () => $this->etatMembre?->pays),
            'source' => $this->whenLoaded('sourceFinancement', fn () => $this->sourceFinancement?->libelle),
            'etag' => $this->updated_at?->toIso8601String(),
            'plan_actif' => $this->when($this->relationLoaded('plansApurement'), fn () => $planActif ? [
                'id' => $planActif->id,
                'reference' => $planActif->reference,
                'statut' => $planActif->statut,
                'montant_total' => Montant::api($planActif->montant_total),
                'nombre_echeances' => $planActif->nombre_echeances,
            ] : null),
            'relances' => $this->whenLoaded('relances', fn () => $this->relances->map(fn ($r) => [
                'id' => $r->id,
                'niveau' => $r->niveau,
                'canal' => $r->canal,
                'objet' => $r->objet,
                'montant_du' => Montant::api($r->montant_du),
                'date_relance' => $r->date_relance?->toDateString(),
                'automatique' => (bool) $r->automatique,
            ])->values()),
            'actions' => $user ? [
                'recouvrer' => $user->can('recouvrer', $this->resource) && ! in_array($this->statut, ['solde', 'annule'], true),
                'relancer' => $user->can('recouvrer', $this->resource) && ! in_array($this->statut, ['solde', 'annule'], true) && $this->solde > 0,
                'annuler' => $user->can('annuler', $this->resource),
                'etablir_plan' => $user->can('recouvrer', $this->resource)
                    && ! in_array($this->statut, ['solde', 'annule'], true)
                    && $this->solde > 0
                    && $this->relationLoaded('plansApurement')
                    && $planActif === null,
            ] : [
                'recouvrer' => false,
                'relancer' => false,
                'annuler' => false,
                'etablir_plan' => false,
            ],
        ];
    }
}
