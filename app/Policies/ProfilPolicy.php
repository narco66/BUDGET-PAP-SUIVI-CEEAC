<?php

namespace App\Policies;

use App\Models\Profil;
use App\Models\User;

class ProfilPolicy
{
    public function viewAny(User $user): bool { return $user->can('profil.voir'); }
    public function view(User $user, Profil $p): bool { return $user->can('profil.voir'); }
    public function create(User $user): bool { return $user->can('profil.creer'); }
    public function update(User $user, Profil $p): bool { return $user->can('profil.modifier'); }
    public function delete(User $user, Profil $p): bool { return $user->can('profil.supprimer'); }
}
