<?php

namespace App\Policies;

use App\Models\Mesure;
use App\Models\User;

class MesurePolicy
{
    public function create(User $user): bool
    {
        return $user->can('indicateur.creer') || $user->can('suivi.creer');
    }

    public function update(User $user, Mesure $mesure): bool
    {
        return $user->can('indicateur.modifier') && $mesure->statut === 'saisie';
    }

    public function delete(User $user, Mesure $mesure): bool
    {
        return $user->can('indicateur.supprimer') && $mesure->statut !== 'validee';
    }

    public function verify(User $user, Mesure $mesure): bool
    {
        return $user->can('indicateur.valider') || $user->can('suivi.valider');
    }

    public function validateMeasure(User $user, Mesure $mesure): bool
    {
        return $user->can('indicateur.valider') || $user->can('suivi.valider');
    }
}
