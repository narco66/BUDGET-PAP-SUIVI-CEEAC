<?php

namespace App\Policies;

use App\Models\TypeIndicateur;
use App\Models\User;

class TypeIndicateurPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('indicateur.voir') || $user->can('rbm.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('indicateur.creer') || $user->can('rbm.admin');
    }

    public function update(User $user, TypeIndicateur $type): bool
    {
        return $user->can('indicateur.modifier') || $user->can('rbm.admin');
    }

    public function delete(User $user, TypeIndicateur $type): bool
    {
        return $user->can('indicateur.supprimer') || $user->can('rbm.admin');
    }
}
