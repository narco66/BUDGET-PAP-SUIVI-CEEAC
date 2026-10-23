<?php

namespace App\Policies;

use App\Models\Programme;
use App\Models\User;

class ProgrammePolicy
{
    public function viewAny(User $user): bool { return $user->can('structure.voir'); }
    public function view(User $user, Programme $p): bool { return $user->can('structure.voir'); }
    public function create(User $user): bool { return $user->can('structure.creer'); }
    public function update(User $user, Programme $p): bool { return $user->can('structure.modifier'); }
    public function delete(User $user, Programme $p): bool { return $user->can('structure.supprimer'); }
}
