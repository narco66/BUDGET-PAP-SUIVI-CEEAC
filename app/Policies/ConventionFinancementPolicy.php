<?php

namespace App\Policies;

use App\Models\ConventionFinancement;
use App\Models\User;

class ConventionFinancementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('convention_financement.voir');
    }

    public function view(User $user, ConventionFinancement $convention): bool
    {
        return $user->can('convention_financement.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('convention_financement.creer');
    }

    public function update(User $user, ConventionFinancement $convention): bool
    {
        return $user->can('convention_financement.modifier');
    }

    public function delete(User $user, ConventionFinancement $convention): bool
    {
        return $user->can('convention_financement.supprimer');
    }
}
