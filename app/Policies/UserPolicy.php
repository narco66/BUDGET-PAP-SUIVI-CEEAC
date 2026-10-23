<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool { return $user->can('utilisateur.voir'); }
    public function view(User $user, User $model): bool { return $user->can('utilisateur.voir'); }
    public function create(User $user): bool { return $user->can('utilisateur.creer'); }
    public function update(User $user, User $model): bool { return $user->can('utilisateur.modifier'); }

    public function delete(User $user, User $model): bool
    {
        // On ne peut pas se supprimer/désactiver soi-même.
        return $user->can('utilisateur.supprimer') && $user->id !== $model->id;
    }
}
