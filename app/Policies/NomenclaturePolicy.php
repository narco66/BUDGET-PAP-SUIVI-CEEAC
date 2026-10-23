<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Politique d'autorisation unique des quatre niveaux de la nomenclature budgétaire
 * (Titre / Article / Paragraphe / Rubrique), enregistrée pour chacun dans AppServiceProvider.
 * Le bypass administrateur est assuré par Gate::before. Les permissions sont en français,
 * conformément à la convention du projet ({module}.{action}).
 */
class NomenclaturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('nomenclature.voir');
    }

    public function view(User $user, Model $element): bool
    {
        return $user->can('nomenclature.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('nomenclature.creer');
    }

    public function update(User $user, Model $element): bool
    {
        return $user->can('nomenclature.modifier');
    }

    public function delete(User $user, Model $element): bool
    {
        return $user->can('nomenclature.supprimer');
    }

    public function restore(User $user, Model $element): bool
    {
        return $user->can('nomenclature.modifier');
    }

    /** Validation du workflow (brouillon → validé) — séparation des fonctions création/validation. */
    public function validate(User $user, Model $element): bool
    {
        return $user->can('nomenclature.valider');
    }
}
