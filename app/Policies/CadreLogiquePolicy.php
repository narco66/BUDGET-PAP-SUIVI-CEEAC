<?php

namespace App\Policies;

use App\Models\User;

class CadreLogiquePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cadre_logique.voir') || $user->can('rbm.voir');
    }

    public function update(User $user): bool
    {
        return $user->can('cadre_logique.gerer')
            || $user->can('rbm.admin')
            || $user->can('rbm.modifier');
    }
}
