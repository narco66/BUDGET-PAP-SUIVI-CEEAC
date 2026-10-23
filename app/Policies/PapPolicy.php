<?php

namespace App\Policies;

use App\Models\Pap;
use App\Models\User;
use App\Services\WorkflowService;

class PapPolicy
{
    public function viewAny(User $user): bool { return $user->can('pap.voir'); }
    public function view(User $user, Pap $pap): bool { return $user->can('pap.voir'); }
    public function create(User $user): bool { return $user->can('pap.creer'); }

    public function update(User $user, Pap $pap): bool
    {
        return $user->can('pap.modifier') && ($pap->estModifiable() || $user->hasRole('administrateur'));
    }

    public function delete(User $user, Pap $pap): bool
    {
        return $user->can('pap.supprimer') && $pap->peutEtreSupprime();
    }

    public function soumettre(User $user, Pap $pap): bool
    {
        return $user->can('pap.modifier') && $pap->estModifiable();
    }

    public function agir(User $user, Pap $pap): bool
    {
        if (! $user->can('pap.voir')) {
            return false;
        }

        $instance = $pap->workflowActif;
        if (! $instance) {
            return false;
        }

        if ($user->hasRole('administrateur')) {
            return true;
        }

        if (! $user->can('pap.valider')) {
            return false;
        }

        return app(WorkflowService::class)->peutAgir($instance, $user);
    }

    public function reviser(User $user, Pap $pap): bool
    {
        return $user->can('pap.modifier') && $pap->peutDemanderRevision();
    }

    /** Risques, mesures correctives et causes d'écarts (GAR P3). */
    public function gererGar(User $user, Pap $pap): bool
    {
        return $user->can('pap.modifier');
    }
}
