<?php

namespace App\Policies;

use App\Models\PartenaireFinancier;
use App\Models\User;

class PartenaireFinancierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('partenaire_financier.voir');
    }

    public function view(User $user, PartenaireFinancier $partenaire): bool
    {
        return $user->can('partenaire_financier.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('partenaire_financier.creer');
    }

    public function update(User $user, PartenaireFinancier $partenaire): bool
    {
        return $user->can('partenaire_financier.modifier');
    }

    public function delete(User $user, PartenaireFinancier $partenaire): bool
    {
        return $user->can('partenaire_financier.supprimer');
    }
}
