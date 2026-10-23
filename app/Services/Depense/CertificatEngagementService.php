<?php

namespace App\Services\Depense;

use App\Models\CertificatEngagement;
use App\Models\Engagement;
use App\Models\User;
use App\Support\Numero;

/** Persistance des certificats d'engagement / réservation (phase D1). */
class CertificatEngagementService
{
    public function enregistrer(Engagement $engagement, string $type, ?User $emetteur = null): CertificatEngagement
    {
        $annee = $engagement->exercice?->annee ?? (int) date('Y');
        $prefixe = $type === 'reservation' ? 'CER' : 'CEG';

        return CertificatEngagement::create([
            'engagement_id' => $engagement->id,
            'numero' => Numero::suivant('certificats_engagement', $prefixe, $annee),
            'type' => $type,
            'emis_par' => $emetteur?->id ?? $engagement->controleur_id,
            'emis_le' => now(),
            'metadata' => [
                'engagement_numero' => $engagement->numero,
                'montant_total' => (float) $engagement->montant_total,
                'objet' => $engagement->objet,
            ],
        ]);
    }
}
