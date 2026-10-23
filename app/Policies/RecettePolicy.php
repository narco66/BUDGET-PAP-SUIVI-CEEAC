<?php

namespace App\Policies;

use App\Models\Recette;
use App\Models\User;

class RecettePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recette.voir');
    }

    public function view(User $user, Recette $recette): bool
    {
        return $user->can('recette.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('recette.creer');
    }

    public function update(User $user, Recette $recette): bool
    {
        return $user->can('recette.modifier') && ! $recette->estVerrouillee();
    }

    public function valider(User $user, Recette $recette): bool
    {
        return $user->can('recette.valider');
    }

    public function encaisser(User $user, Recette $recette): bool
    {
        return $user->can('recette.encaissement');
    }

    /** Aucune suppression d'une recette comptabilisée (réalisée / partiellement réalisée). */
    public function delete(User $user, Recette $recette): bool
    {
        return $user->can('recette.supprimer') && ! $recette->estVerrouillee();
    }

    public function export(User $user): bool
    {
        return $user->can('recette.exporter');
    }
}
