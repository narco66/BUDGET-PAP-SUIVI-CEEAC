<?php

namespace App\Policies;

use App\Models\Collecte;
use App\Models\User;

class CollectePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suivi.voir') || $user->can('suivi_physique.view');
    }

    public function view(User $user, Collecte $collecte): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('suivi.creer') || $user->can('suivi_physique.create');
    }

    public function update(User $user, Collecte $collecte): bool
    {
        return $this->create($user) && $collecte->statut !== 'validee';
    }

    public function validate(User $user, Collecte $collecte): bool
    {
        return $user->can('suivi.valider')
            || $user->can('suivi_physique.validate')
            || $user->can('workflow.validate');
    }
}
