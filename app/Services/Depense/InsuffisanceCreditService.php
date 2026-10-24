<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Support\Montant;
use App\Support\AuditLogger;

/**
 * Enregistre une insuffisance de crédits. Ne crée jamais de crédit,
 * de virement ni de dépassement — la reprise passe par une opération budgétaire régulière.
 */
class InsuffisanceCreditService
{
    public function constater(Engagement $engagement, LigneBudgetaire $ligne, float $demande): void
    {
        $disponible = (float) $ligne->disponible;
        $engagement->forceFill([
            'statut' => 'en_attente_arbitrage',
            'diagnostic_credits' => [
                'ligne_id' => $ligne->id,
                'ligne_code' => $ligne->code,
                'disponible' => Montant::api($disponible),
                'demande' => Montant::api($demande),
                'deficit' => Montant::api(max(0, $demande - $disponible)),
                'message' => 'Engagement bloqué : crédits insuffisants. Aucun dépassement ni crédit artificiel n’a été créé. Reprendre après une révision, un virement ou un crédit complémentaire dûment validé.',
                'constate_le' => now()->toIso8601String(),
            ],
        ])->save();

        AuditLogger::enregistrer('engagement_insuffisance_credits', $engagement, null, [
            'ligne' => $ligne->code,
            'deficit' => $demande - $disponible,
        ]);
    }
}
