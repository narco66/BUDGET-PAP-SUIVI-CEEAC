<?php

namespace App\Services\Strategie;

use App\Models\Strategie\PlanStrategique;
use Illuminate\Validation\ValidationException;

class StrategieValidationService
{
    public function peutSoumettre(PlanStrategique $plan): bool
    {
        $plan->loadCount(['orientations', 'objectifs']);

        return $plan->orientations_count > 0
            && $plan->objectifs_count > 0
            && in_array($plan->statut, ['brouillon', 'rejete'], true);
    }

    public function soumettre(PlanStrategique $plan): void
    {
        if (! $this->peutSoumettre($plan)) {
            throw ValidationException::withMessages([
                'plan' => 'Le plan doit être en brouillon/rejeté, avec au moins une orientation et un objectif.',
            ]);
        }

        $plan->update([
            'statut' => 'en_validation',
            'submitted_at' => now(),
            'motif_rejet' => null,
            'updated_by' => auth()->id(),
        ]);
    }

    public function valider(PlanStrategique $plan): void
    {
        if ($plan->statut !== 'en_validation') {
            throw ValidationException::withMessages([
                'plan' => 'Seuls les plans en validation peuvent être approuvés.',
            ]);
        }

        $plan->update([
            'statut' => 'actif',
            'validated_at' => now(),
            'validated_by' => auth()->id(),
            'motif_rejet' => null,
            'updated_by' => auth()->id(),
        ]);
    }

    public function rejeter(PlanStrategique $plan, ?string $motif): void
    {
        if ($plan->statut !== 'en_validation') {
            throw ValidationException::withMessages([
                'plan' => 'Seuls les plans en validation peuvent être rejetés.',
            ]);
        }

        $plan->update([
            'statut' => 'rejete',
            'motif_rejet' => $motif,
            'updated_by' => auth()->id(),
        ]);
    }
}
