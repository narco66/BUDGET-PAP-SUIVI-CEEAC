<?php

namespace App\Http\Resources;

use App\Depense\Etapes;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Services\Depense\DepenseQuotaService;
use App\Services\Depense\FractionnementOrdonnancementService;
use App\Support\Montant;
use App\Support\WorkflowResume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EtapeDepenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $type = $this->type();
        $config = Etapes::get($type);
        $enfantType = $config['enfant_type'];
        $peutEnfant = $enfantType
            && $this->statut === 'valide'
            && $user?->can(Etapes::get($enfantType)['permission'].'.creer');

        return [
            'id' => $this->id,
            'type' => $type,
            'numero' => $this->numero,
            'montant' => Montant::api($this->montant),
            'montant_brut' => $this->montant_brut !== null ? Montant::api($this->montant_brut) : null,
            'montant_retenues' => $this->montant_retenues !== null ? Montant::api($this->montant_retenues) : null,
            'montant_net' => $this->montant_net !== null ? Montant::api($this->montant_net) : null,
            'montant_xaf' => $this->montant_xaf !== null ? Montant::api($this->montant_xaf) : null,
            'statut' => $this->statut,
            'date' => $this->{$config['date_cle']}?->toDateString(),
            'parent_type' => $config['parent_type'],
            'parent_id' => $this->{$config['parent_cle']},
            'parent_numero' => match ($this->resource::class) {
                Liquidation::class => $this->whenLoaded('engagement', fn () => $this->engagement?->numero),
                Ordonnancement::class => $this->whenLoaded('liquidation', fn () => $this->liquidation?->numero),
                Paiement::class => $this->whenLoaded('ordonnancement', fn () => $this->ordonnancement?->numero),
                default => null,
            },
            'enfant_type' => $enfantType,
            'service_fait' => $this->service_fait ?? null,
            'numero_tranche' => $this->numero_tranche ?? null,
            'numero_facture' => $this->numero_facture ?? null,
            'alerte_facture_doublon' => $this->resource instanceof Liquidation
                ? app(\App\Services\Depense\FactureDoublonService::class)->alerte($this->resource)
                : null,
            'seuil_xaf' => $this->seuil_xaf !== null ? Montant::api($this->seuil_xaf) : null,
            'ordonnateur_role' => $this->ordonnateur_role ?? null,
            'etat_execution' => $this->etat_execution ?? null,
            'restant_a_payer' => $this->resource instanceof Ordonnancement
                ? Montant::api(app(DepenseQuotaService::class)->montantRestantPaiement($this->resource))
                : ($this->resource instanceof Paiement && $this->ordonnancement
                    ? Montant::api(app(DepenseQuotaService::class)->montantRestantPaiement($this->ordonnancement, $this->id))
                    : null),
            'restant_a_liquider' => $this->resource instanceof Liquidation && $this->engagement
                ? Montant::api($this->engagement->montantRestantLiquidation($this->id))
                : null,
            'alerte_fractionnement' => $this->resource instanceof Ordonnancement
                ? app(FractionnementOrdonnancementService::class)->alerte($this->resource)
                : null,
            'mode_paiement' => $this->mode_paiement ?? null,
            'motif_partiel' => $this->motif_partiel ?? null,
            'rib' => $this->rib ?? null,
            'alerte_rib' => $this->resource instanceof Paiement
                ? app(\App\Services\Depense\RibControleService::class)->alerte($this->resource)
                : null,
            'rejets' => $this->resource instanceof Paiement
                ? $this->whenLoaded('rejets', fn () => $this->rejets->map(fn ($r) => [
                    'type' => $r->type ?? 'workflow',
                    'motif' => $r->motif,
                    'preuve' => $r->preuve,
                    'rejete_le' => $r->rejete_le?->toIso8601String(),
                ]))
                : null,
            'devise' => $this->devise ?? null,
            'reference_bancaire' => $this->reference_bancaire ?? null,
            'version' => $this->updated_at?->toIso8601String(),
            'verification_url' => '/api/v1/verification/'.$this->type().'/'.$this->id,
            'workflow' => WorkflowResume::api($this->resource, $user),
            'actions' => $user ? [
                'soumettre' => $user->can('update', $this->resource),
                'agir' => $user->can('agir', $this->resource),
                'creer_enfant' => (bool) $peutEnfant,
                'contrepasser' => $this->resource instanceof Paiement && $user->can('contrepasser', $this->resource),
            ] : [
                'soumettre' => false,
                'agir' => false,
                'creer_enfant' => false,
                'contrepasser' => false,
            ],
        ];
    }

    private function type(): string
    {
        return match ($this->resource::class) {
            Liquidation::class => 'liquidation',
            Ordonnancement::class => 'ordonnancement',
            Paiement::class => 'paiement',
            default => 'liquidation',
        };
    }
}
