<?php

namespace App\Policies;

use App\Models\Rapport;
use App\Models\User;

class RapportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reporting.view');
    }

    public function view(User $user, Rapport $rapport): bool
    {
        return $user->can('reporting.view');
    }

    public function generate(User $user): bool
    {
        return $user->can('reporting.generate');
    }

    public function export(User $user): bool
    {
        return $user->can('reporting.export');
    }

    public function sign(User $user, Rapport $rapport): bool
    {
        return $user->can('reporting.generate') && ! $rapport->estVerrouille();
    }

    public function validate(User $user, Rapport $rapport): bool
    {
        return $user->can('reporting.validate')
            && ($rapport->statut_workflow ?? 'genere') === 'genere'
            && ! $rapport->estVerrouille();
    }

    public function publish(User $user, Rapport $rapport): bool
    {
        return $user->can('reporting.publish') && $rapport->statut_workflow === 'valide';
    }

    public function download(User $user, Rapport $rapport): bool
    {
        return $user->can('reporting.view');
    }
}
