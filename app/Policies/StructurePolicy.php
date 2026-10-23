<?php

namespace App\Policies;

use App\Models\Structure;
use App\Models\User;
use App\Support\ReferentielAdministratifAuthorization;

class StructurePolicy
{
    public function viewAny(User $user): bool
    {
        return ReferentielAdministratifAuthorization::peut($user, 'voir');
    }

    public function view(User $user, Structure $structure): bool
    {
        return ReferentielAdministratifAuthorization::peut($user, 'voir');
    }

    public function create(User $user): bool
    {
        return ReferentielAdministratifAuthorization::peut($user, 'creer');
    }

    public function update(User $user, Structure $structure): bool
    {
        return ReferentielAdministratifAuthorization::peut($user, 'modifier');
    }

    public function delete(User $user, Structure $structure): bool
    {
        return ReferentielAdministratifAuthorization::peut($user, 'supprimer');
    }
}
