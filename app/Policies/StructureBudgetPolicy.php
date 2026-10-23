<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Services\AccessScopeService;

class StructureBudgetPolicy
{
    public function __construct(private readonly AccessScopeService $scopes)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('structure_budget.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('structure_budget.creer');
    }

    public function export(User $user): bool
    {
        return $user->can('structure_budget.exporter');
    }

    public function import(User $user): bool
    {
        return $user->can('structure_budget.importer') || $user->can('structure_budget.creer');
    }

    public function viewLigne(User $user, LigneBudgetaire $ligne): bool
    {
        return $user->can('structure_budget.voir') && $this->dansPerimetre($user, $ligne);
    }

    public function updateLigne(User $user, LigneBudgetaire $ligne): bool
    {
        return $user->can('structure_budget.modifier')
            && $this->dansPerimetre($user, $ligne)
            && $ligne->estModifiable();
    }

    public function submitLigne(User $user, LigneBudgetaire $ligne): bool
    {
        return $user->can('structure_budget.modifier')
            && $this->dansPerimetre($user, $ligne)
            && $ligne->estSoumissible();
    }

    public function validateLigne(User $user, LigneBudgetaire $ligne): bool
    {
        return $user->can('structure_budget.valider')
            && $this->dansPerimetre($user, $ligne)
            && $ligne->statut_validation === 'soumis';
    }

    public function rejectLigne(User $user, LigneBudgetaire $ligne): bool
    {
        return ($user->can('structure_budget.rejeter') || $user->can('structure_budget.valider'))
            && $this->dansPerimetre($user, $ligne)
            && $ligne->statut_validation === 'soumis';
    }

    public function viewJournal(User $user): bool
    {
        return $user->can('structure_budget.consulter_journal') || $user->can('audit.voir');
    }

    public function deleteLigne(User $user, LigneBudgetaire $ligne): bool
    {
        if (Budget::where('exercice_id', $ligne->exercice_id)->where('statut', 'adopte')->exists()) {
            return false;
        }

        return $user->can('structure_budget.supprimer')
            && $this->dansPerimetre($user, $ligne)
            && $ligne->peutEtreSupprimee();
    }

    public function archiveLigne(User $user, LigneBudgetaire $ligne): bool
    {
        return $user->can('structure_budget.modifier') && $this->dansPerimetre($user, $ligne);
    }

    private function dansPerimetre(User $user, LigneBudgetaire $ligne): bool
    {
        return $this->scopes->dansPerimetre($user, $ligne->structure_id);
    }
}
