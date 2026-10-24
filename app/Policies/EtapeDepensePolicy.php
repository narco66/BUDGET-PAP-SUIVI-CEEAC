<?php

namespace App\Policies;

use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Model;

/** Policy générique pour les étapes aval de la dépense (liquidation, ordonnancement, paiement). */
abstract class EtapeDepensePolicy
{
    abstract protected function permissionPrefix(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permissionPrefix().'.voir');
    }

    public function view(User $user, Model $item): bool
    {
        return $user->can($this->permissionPrefix().'.voir');
    }

    public function create(User $user): bool
    {
        return $user->can($this->permissionPrefix().'.creer');
    }

    public function update(User $user, Model $item): bool
    {
        return $user->can($this->permissionPrefix().'.creer')
            && method_exists($item, 'estModifiable')
            && $item->estModifiable();
    }

    public function agir(User $user, Model $item): bool
    {
        $instance = $item->workflowActif;
        if (! $instance) {
            return false;
        }

        if ($user->can('depenses.admin')) {
            return true;
        }

        return app(WorkflowService::class)->peutAgir($instance, $user);
    }
}
