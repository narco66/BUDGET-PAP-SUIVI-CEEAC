<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool { return $user->can('role.voir'); }
    public function view(User $user, Role $role): bool { return $user->can('role.voir'); }
    public function create(User $user): bool { return $user->can('role.creer'); }

    /** Les rôles protégés ne sont modifiables que par un administrateur. */
    public function update(User $user, Role $role): bool
    {
        if ($role->protege && ! $user->estAdministrateur()) {
            return false;
        }

        return $user->can('role.modifier');
    }

    /** Un rôle protégé ou de type système n'est jamais supprimable. */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('role.supprimer') && ! $role->protege && $role->type !== 'systeme';
    }

    /** Gérer la matrice des permissions d'un rôle. */
    public function gererPermissions(User $user, Role $role): bool
    {
        if ($role->protege && ! $user->estAdministrateur()) {
            return false;
        }

        return $user->can('role.modifier');
    }
}
