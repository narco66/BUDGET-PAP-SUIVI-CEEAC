<?php

namespace App\Policies;

use App\Models\CategorieRecette;
use App\Models\User;

class CategorieRecettePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recette.voir');
    }

    public function view(User $user, CategorieRecette $categorie): bool
    {
        return $user->can('recette.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('recette.admin');
    }

    public function update(User $user, CategorieRecette $categorie): bool
    {
        return $user->can('recette.admin');
    }

    public function delete(User $user, CategorieRecette $categorie): bool
    {
        return $user->can('recette.admin');
    }
}
