<?php

namespace App\Http\Resources;

use App\Models\EcheanceApurement;
use App\Models\PlanApurement;
use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanApurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $total = (float) $this->montant_total;
        $regle = $this->montantRegle();
        $solde = $this->solde();
        $verrouille = $this->estVerrouille();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'statut' => $this->statut,
            'statut_libelle' => PlanApurement::STATUTS[$this->statut] ?? $this->statut,
            'montant_total' => Montant::api($total),
            'montant_regle' => Montant::api($regle),
            'solde' => Montant::api($solde),
            'nombre_echeances' => $this->nombre_echeances,
            'date_debut' => $this->date_debut?->toDateString(),
            'observations' => $this->observations,
            'en_defaut' => $this->enDefaut(),
            'appel_fonds_id' => $this->appel_fonds_id,
            'appel' => $this->whenLoaded('appel', fn () => [
                'id' => $this->appel?->id,
                'reference' => $this->appel?->reference,
                'objet' => $this->appel?->objet,
                'solde' => Montant::api($this->appel?->solde),
            ]),
            'etabli_par' => $this->whenLoaded('etabliPar', fn () => $this->etabliPar?->name),
            'etag' => $this->updated_at?->toIso8601String(),
            'echeances' => $this->whenLoaded('echeances', fn () => $this->echeances->map(function ($ech) use ($user) {
                $prevu = (float) $ech->montant_prevu;
                $regleEch = (float) $ech->montant_regle;

                return [
                    'id' => $ech->id,
                    'rang' => $ech->rang,
                    'date_echeance' => $ech->date_echeance?->toDateString(),
                    'montant_prevu' => Montant::api($prevu),
                    'montant_regle' => Montant::api($regleEch),
                    'solde' => Montant::api($ech->solde()),
                    'statut' => $ech->statut,
                    'statut_libelle' => EcheanceApurement::STATUTS[$ech->statut] ?? $ech->statut,
                    'date_reglement' => $ech->date_reglement?->toDateString(),
                    'en_retard' => $ech->enRetard(),
                    'etag' => $ech->updated_at?->toIso8601String(),
                    'actions' => [
                        'regler' => (bool) $user?->can('recette.recouvrement')
                            && $this->statut === 'actif'
                            && $ech->statut !== 'honoree',
                    ],
                ];
            })->values()),
            'actions' => [
                'annuler' => (bool) $user?->can('recette.recouvrement')
                    && $this->statut === 'actif'
                    && ! $verrouille,
            ],
        ];
    }
}
