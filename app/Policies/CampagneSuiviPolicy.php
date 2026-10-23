<?php

namespace App\Policies;

use App\Models\CampagneSuivi;
use App\Models\User;

class CampagneSuiviPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suivi.voir') || $user->can('suivi_physique.view');
    }

    public function view(User $user, CampagneSuivi $c): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('suivi.creer') || $user->can('suivi_physique.create');
    }

    public function update(User $user, CampagneSuivi $c): bool
    {
        return ($user->can('suivi.creer') || $user->can('suivi_physique.update')) && $c->estOuverte();
    }

    public function delete(User $user, CampagneSuivi $c): bool
    {
        return ($user->can('suivi.valider') || $user->can('suivi_physique.admin')) && $c->estOuverte();
    }

    public function export(User $user, CampagneSuivi $c): bool
    {
        return ($user->can('suivi.exporter') || $user->can('suivi_physique.export')) && $this->view($user, $c);
    }
}
