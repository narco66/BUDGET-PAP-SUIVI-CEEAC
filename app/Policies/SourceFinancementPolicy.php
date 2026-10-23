<?php

namespace App\Policies;

use App\Models\SourceFinancement;
use App\Models\User;

class SourceFinancementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('source_financement.voir');
    }

    public function view(User $user, SourceFinancement $source): bool
    {
        return $user->can('source_financement.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('source_financement.creer');
    }

    public function update(User $user, SourceFinancement $source): bool
    {
        return $user->can('source_financement.modifier');
    }

    public function delete(User $user, SourceFinancement $source): bool
    {
        return $user->can('source_financement.supprimer');
    }
}
