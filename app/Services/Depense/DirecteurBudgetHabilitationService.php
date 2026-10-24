<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\User;
use App\Models\WorkflowInstanceEtape;
use RuntimeException;

/**
 * Contrôle organique optionnel du visa Direction du Budget.
 * Si l’utilisateur n’a pas de structure, le rôle Spatie suffit (tests / comptes incomplets).
 * Le mapping des codes reste configurable, pas une décision institutionnelle figée.
 */
class DirecteurBudgetHabilitationService
{
    public function assertVisa(User $user, WorkflowInstanceEtape $etape, mixed $objet): void
    {
        if (! $objet instanceof Engagement) {
            return;
        }

        $roleAttendu = (string) config('chaine_depense.role_directeur_budget', 'directeur');
        if ($etape->role !== $roleAttendu) {
            return;
        }

        if (! $user->structure_id) {
            return;
        }

        $user->loadMissing('structure');
        $code = strtoupper(trim((string) ($user->structure?->code ?: $user->structure?->sigle ?: '')));
        $autorises = array_map(
            static fn ($c) => strtoupper(trim((string) $c)),
            (array) config('chaine_depense.codes_structure_directeur_budget', ['DIR-BUDGET'])
        );

        if ($code === '' || in_array($code, $autorises, true)) {
            return;
        }

        throw new RuntimeException(
            'Le visa Direction du Budget est réservé aux agents rattachés à une structure Budget (codes configurés). Mapping institutionnel à valider.'
        );
    }
}
