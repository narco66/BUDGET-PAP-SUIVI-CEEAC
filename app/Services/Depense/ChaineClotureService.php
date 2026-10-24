<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Support\AuditLogger;
use App\Support\Montant;

/** Clôture de la chaîne EB→PAY lorsque tout est liquidé et payé. Idempotente. */
class ChaineClotureService
{
    public function __construct(private readonly DepenseQuotaService $quotas) {}

    /** @return array{soldee:bool,restant_a_liquider:string,restant_a_payer:string,cloturee_le:?string} */
    public function etat(Engagement $engagement): array
    {
        $restantLiq = $engagement->montantRestantLiquidation();
        $restantPay = 0.0;
        $engagement->loadMissing('liquidations.ordonnancements.paiements');

        foreach ($engagement->liquidations()->whereNotIn('statut', ['rejete', 'annule'])->get() as $liq) {
            foreach ($liq->ordonnancements()->whereNotIn('statut', ['rejete', 'annule'])->get() as $ord) {
                $restantPay += $this->quotas->montantRestantPaiement($ord);
            }
        }

        $aUnPaiementValide = $engagement->liquidations()
            ->whereHas('ordonnancements.paiements', function ($q) {
                $q->where('statut', 'valide')
                    ->where(function ($inner) {
                        $inner->whereNull('etat_execution')
                            ->orWhereNotIn('etat_execution', ['instrument_rejete', 'en_preparation']);
                    });
            })
            ->exists();

        $soldee = $aUnPaiementValide
            && $restantLiq <= 0.005
            && $restantPay <= 0.005;

        return [
            'soldee' => $soldee,
            'restant_a_liquider' => Montant::api($restantLiq),
            'restant_a_payer' => Montant::api($restantPay),
            'cloturee_le' => $engagement->chaine_cloturee_le?->toIso8601String(),
        ];
    }

    public function assurer(Engagement $engagement): void
    {
        $etat = $this->etat($engagement);
        if (! $etat['soldee'] || $engagement->chaine_cloturee_le) {
            return;
        }

        $engagement->forceFill(['chaine_cloturee_le' => now()])->save();
        AuditLogger::enregistrer('chaine_depense_cloturee', $engagement);
    }

    /** Après contrepassation ou dégagement : la clôture n’est plus vraie, on la lève sans effacer l’audit. */
    public function reouvrirSiNecessaire(Engagement $engagement): void
    {
        $etat = $this->etat($engagement);
        if ($etat['soldee'] || ! $engagement->chaine_cloturee_le) {
            return;
        }

        $engagement->forceFill(['chaine_cloturee_le' => null])->save();
        AuditLogger::enregistrer('chaine_depense_reouverte', $engagement);
    }
}
