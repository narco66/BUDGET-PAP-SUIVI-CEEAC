<?php

namespace App\Policies;

use App\Models\Encaissement;
use App\Models\User;
use App\Services\WorkflowService;

class EncaissementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recette.voir');
    }

    public function view(User $user, Encaissement $encaissement): bool
    {
        return $user->can('recette.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('recette.encaissement');
    }

    /** Soumission au circuit RECETTES (gestionnaire / saisisseur). */
    public function soumettre(User $user, Encaissement $encaissement): bool
    {
        return $user->can('recette.encaissement')
            && $encaissement->estModifiable()
            && ! $encaissement->workflowActif;
    }

    /** Décision sur l'étape courante du circuit actif. */
    public function agir(User $user, Encaissement $encaissement): bool
    {
        $instance = $encaissement->workflowActif;

        return $instance && app(WorkflowService::class)->peutAgir($instance, $user);
    }

    /** @deprecated Préférer soumettre() puis le circuit workflow. */
    public function valider(User $user, Encaissement $encaissement): bool
    {
        return $this->soumettre($user, $encaissement);
    }

    public function rapprocher(User $user, Encaissement $encaissement): bool
    {
        return $user->can('recette.encaissement');
    }

    public function annuler(User $user, Encaissement $encaissement): bool
    {
        return $user->can('recette.encaissement') && ! $encaissement->estVerrouille();
    }

    public function delete(User $user, Encaissement $encaissement): bool
    {
        return $user->can('recette.supprimer') && ! $encaissement->estVerrouille();
    }
}
