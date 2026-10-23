<?php

namespace App\Policies;

use App\Models\Engagement;
use App\Models\User;
use App\Services\WorkflowService;

class EngagementPolicy
{
    public function viewAny(User $user): bool { return $user->can('engagement.voir'); }
    public function view(User $user, Engagement $e): bool { return $user->can('engagement.voir'); }
    public function create(User $user): bool
    {
        return $user->can('engagement.creer') || $user->can('depenses.admin');
    }

    public function update(User $user, Engagement $e): bool
    {
        return $user->can('engagement.creer') && $e->estModifiable();
    }

    /** Visa / rejet sur l'étape courante du circuit engagement. */
    public function agir(User $user, Engagement $e): bool
    {
        $instance = $e->workflowActif;
        if (! $instance) {
            return false;
        }

        if ($user->can('depenses.admin')) {
            return true;
        }

        return app(WorkflowService::class)->peutAgir($instance, $user);
    }

    /** Annulation : créateur (ou droit de modif) tant que non payé. */
    public function annuler(User $user, Engagement $e): bool
    {
        return $user->can('engagement.creer') && in_array($e->statut, ['brouillon', 'soumis', 'vise', 'rejete'], true);
    }
}
