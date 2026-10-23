<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Services\AccessScopeService;

class LigneBudgetairePolicy
{
    public function __construct(private readonly AccessScopeService $scopes)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ligne_budgetaire.voir');
    }

    public function view(User $user, LigneBudgetaire $l): bool
    {
        return $user->can('ligne_budgetaire.voir') && $this->dansPerimetre($user, $l);
    }

    public function create(User $user): bool
    {
        return $user->can('ligne_budgetaire.creer');
    }

    public function update(User $user, LigneBudgetaire $l): bool
    {
        return $user->can('ligne_budgetaire.modifier') && $this->dansPerimetre($user, $l);
    }

    public function delete(User $user, LigneBudgetaire $l): bool
    {
        if (Budget::where('exercice_id', $l->exercice_id)->where('statut', 'adopte')->exists()) {
            return false;
        }

        return $user->can('ligne_budgetaire.supprimer') && $this->dansPerimetre($user, $l) && $l->peutEtreSupprimee();
    }

    private function dansPerimetre(User $user, LigneBudgetaire $l): bool
    {
        return $this->scopes->dansPerimetre($user, $l->structure_id);
    }
}
