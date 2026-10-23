<?php

namespace App\Policies;

use App\Models\Exercice;
use App\Models\User;

class ExercicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exercice.voir');
    }

    public function view(User $user, Exercice $exercice): bool
    {
        return $user->can('exercice.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('exercice.creer');
    }

    public function update(User $user, Exercice $exercice): bool
    {
        return $user->can('exercice.modifier') && $exercice->statut !== 'clos';
    }

    public function delete(User $user, Exercice $exercice): bool
    {
        return $user->can('exercice.supprimer') && $exercice->statut === 'brouillon';
    }

    public function ouvrir(User $user, Exercice $exercice): bool
    {
        return $user->can('exercice.ouvrir') && $exercice->statut !== 'clos';
    }

    public function cloturer(User $user, Exercice $exercice): bool
    {
        return $user->can('exercice.cloturer') && $exercice->statut === 'ouvert';
    }

    public function activer(User $user, Exercice $exercice): bool
    {
        return $user->can('exercice.activer');
    }
}
