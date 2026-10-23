<?php

namespace App\Policies;

use App\Models\SourceVerification;
use App\Models\UniteMesure;
use App\Models\User;

class UniteMesurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('indicateur.voir') || $user->can('rbm.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('indicateur.creer') || $user->can('rbm.admin');
    }

    public function update(User $user, UniteMesure $unite): bool
    {
        return $user->can('indicateur.modifier') || $user->can('rbm.admin');
    }

    public function delete(User $user, UniteMesure $unite): bool
    {
        return $user->can('indicateur.supprimer') || $user->can('rbm.admin');
    }
}
