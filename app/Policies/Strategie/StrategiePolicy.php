<?php

namespace App\Policies\Strategie;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Policy générique — permissions audit `strategie.*`. */
class StrategiePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('strategie.view') || $user->can('strategie.admin');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('strategie.create') || $user->can('strategie.admin');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('strategie.update') || $user->can('strategie.admin');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('strategie.delete') || $user->can('strategie.admin');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('strategie.archive') || $user->can('strategie.admin');
    }

    public function validate(User $user, Model $model): bool
    {
        return $user->can('strategie.validate') || $user->can('strategie.admin');
    }

    public function export(User $user): bool
    {
        return $user->can('strategie.export') || $user->can('strategie.admin');
    }
}
