<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('cockpit.{exerciceId}', function ($user, string $exerciceId) {
    if (! $user?->can('tableau_bord.voir') && ! $user?->can('reporting.view')) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
