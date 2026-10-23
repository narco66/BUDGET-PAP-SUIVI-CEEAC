<?php

namespace App\Policies;

use App\Models\SourceVerification;
use App\Models\User;

class SourceVerificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('indicateur.voir') || $user->can('rbm.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('indicateur.creer') || $user->can('rbm.admin');
    }

    public function update(User $user, SourceVerification $source): bool
    {
        return $user->can('indicateur.modifier') || $user->can('rbm.admin');
    }

    public function delete(User $user, SourceVerification $source): bool
    {
        return $user->can('indicateur.supprimer') || $user->can('rbm.admin');
    }
}
