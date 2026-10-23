<?php

namespace App\Policies;

use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Services\AccessScopeService;

class ExpressionBesoinPolicy
{
    public function __construct(private readonly AccessScopeService $scopes)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('besoin.voir');
    }

    public function view(User $user, ExpressionBesoin $b): bool
    {
        return $user->can('besoin.voir') && $this->dansPerimetre($user, $b);
    }

    public function create(User $user): bool
    {
        return $user->can('besoin.creer');
    }

    public function update(User $user, ExpressionBesoin $b): bool
    {
        return $user->can('besoin.modifier') && $b->estModifiable() && $this->dansPerimetre($user, $b);
    }

    public function delete(User $user, ExpressionBesoin $b): bool
    {
        return $user->can('besoin.supprimer') && $b->estModifiable() && $this->dansPerimetre($user, $b);
    }

    public function agir(User $user, ExpressionBesoin $b): bool
    {
        $instance = $b->workflowActif;
        if (! $instance) {
            return false;
        }

        if ($user->can('depenses.admin')) {
            return true;
        }

        return app(\App\Services\WorkflowService::class)->peutAgir($instance, $user);
    }

    public function consolider(User $user): bool
    {
        return $user->can('besoin.valider') || $user->can('depenses.admin');
    }

    private function dansPerimetre(User $user, ExpressionBesoin $besoin): bool
    {
        return $this->scopes->dansPerimetre($user, $besoin->structure_id);
    }
}
