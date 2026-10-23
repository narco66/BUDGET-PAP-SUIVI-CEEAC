<?php

namespace App\Policies;

use App\Models\PrevisionRecette;
use App\Models\User;

class PrevisionRecettePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recette.voir');
    }

    public function view(User $user, PrevisionRecette $prevision): bool
    {
        return $user->can('recette.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('recette.creer');
    }

    /** Une prévision validée ou révisée ne se modifie plus : elle se révise (nouvelle version). */
    public function update(User $user, PrevisionRecette $prevision): bool
    {
        return $user->can('recette.modifier') && ! $prevision->estVerrouillee();
    }

    public function reviser(User $user, PrevisionRecette $prevision): bool
    {
        return $user->can('recette.modifier') && $prevision->est_courante;
    }

    public function valider(User $user, PrevisionRecette $prevision): bool
    {
        return $user->can('recette.valider');
    }

    public function delete(User $user, PrevisionRecette $prevision): bool
    {
        return $user->can('recette.supprimer') && ! $prevision->estVerrouillee();
    }
}
