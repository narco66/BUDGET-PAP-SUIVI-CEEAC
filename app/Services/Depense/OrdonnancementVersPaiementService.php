<?php

namespace App\Services\Depense;

use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;

class OrdonnancementVersPaiementService
{
    public function assurerDossierDepuisOrdreSigne(Ordonnancement $ordonnancement): Paiement
    {
        return DB::transaction(function () use ($ordonnancement) {
            $verrouille = Ordonnancement::query()->whereKey($ordonnancement->id)->lockForUpdate()->firstOrFail();

            $existant = Paiement::query()
                ->where('ordonnancement_id', $verrouille->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if ($existant) {
                return $existant;
            }

            $engagement = $verrouille->liquidation?->engagement;
            $annee = $engagement?->exercice?->annee ?? (int) date('Y');
            $ordre = $verrouille->ordresPaiement()->where('statut', 'emis')->first();

            $paiement = Paiement::create([
                'numero' => Numero::suivant('paiements', 'PAY', $annee),
                'ordonnancement_id' => $verrouille->id,
                'ordre_paiement_id' => $ordre?->id,
                'montant' => $verrouille->montant,
                'devise' => 'XAF',
                'taux_change' => 1,
                'montant_xaf' => $verrouille->montant,
                'mode_paiement' => 'virement',
                'statut' => 'brouillon',
                'etat_execution' => 'en_preparation',
                'cree_par' => $verrouille->cree_par,
            ]);
            // Agence comptable prévenue que le paiement est à préparer (référentiel Notifications § 6.5).
            app(\App\Services\Notifications\DepenseNotificationService::class)->notifierPaiementAPreparer($paiement);

            return $paiement;
        });
    }
}
