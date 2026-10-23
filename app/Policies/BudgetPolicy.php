<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Services\WorkflowService;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budget.voir');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->can('budget.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('budget.creer');
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->can('budget.modifier') && ($budget->estModifiable() || $user->hasRole('administrateur'));
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->can('budget.supprimer') && $budget->peutEtreSupprime();
    }

    public function soumettre(User $user, Budget $budget): bool
    {
        return $user->can('budget.modifier') && $budget->estModifiable();
    }

    public function agir(User $user, Budget $budget): bool
    {
        if (! $user->can('budget.voir')) {
            return false;
        }

        $instance = $budget->workflowActif;
        if (! $instance) {
            return false;
        }

        if ($user->hasRole('administrateur')) {
            return true;
        }

        if (! $user->can('budget.valider')) {
            return false;
        }

        return app(WorkflowService::class)->peutAgir($instance, $user);
    }

    public function reviser(User $user, Budget $budget): bool
    {
        return $user->can('budget.modifier') && $budget->peutDemanderRevision();
    }

    public function preparer(User $user, Budget $budget): bool
    {
        return $user->can('budget.modifier') && $budget->peutPreparer();
    }

    public function arbitrer(User $user, Budget $budget): bool
    {
        return ($user->can('budget.arbitrer') || $user->can('budget.valider'))
            && $budget->peutPreparer();
    }

    public function consolider(User $user, Budget $budget): bool
    {
        return ($user->can('budget.consolider') || $user->can('budget.arbitrer'))
            && $budget->peutPreparer();
    }

    public function simuler(User $user, Budget $budget): bool
    {
        return ($user->can('budget.simuler') || $user->can('budget.consolider'))
            && $budget->peutPreparer();
    }

    public function rectifier(User $user, Budget $budget): bool
    {
        return ($user->can('budget.rectifier') || $user->can('budget.valider'))
            && $budget->peutRectifier();
    }

    public function validerPreparation(User $user, Budget $budget, ?string $niveau = null): bool
    {
        if (! $user->can('budget.valider') || ! $budget->peutPreparer()) {
            return false;
        }

        return match ($niveau) {
            'direction' => $user->hasAnyRole(['directeur', 'administrateur']),
            'departement' => $user->hasAnyRole(['commissaire', 'administrateur']),
            'dppb' => $user->hasAnyRole(['controleur_financier', 'administrateur']),
            default => $user->hasAnyRole(['directeur', 'commissaire', 'controleur_financier', 'administrateur']),
        };
    }
}
