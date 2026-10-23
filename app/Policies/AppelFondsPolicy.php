<?php

namespace App\Policies;

use App\Models\AppelFonds;
use App\Models\User;

class AppelFondsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recette.voir');
    }

    public function view(User $user, AppelFonds $appel): bool
    {
        return $user->can('recette.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('recette.recouvrement');
    }

    public function recouvrer(User $user, AppelFonds $appel): bool
    {
        return $user->can('recette.recouvrement');
    }

    public function annuler(User $user, AppelFonds $appel): bool
    {
        return $user->can('recette.recouvrement') && ! $appel->estVerrouille();
    }

    public function delete(User $user, AppelFonds $appel): bool
    {
        return $user->can('recette.supprimer') && ! $appel->estVerrouille();
    }
}
