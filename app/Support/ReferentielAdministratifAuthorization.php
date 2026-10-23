<?php

namespace App\Support;

use App\Models\User;

/** Autorisation référentiel institutionnel : permissions `structure.*` ou alias `administratif.*` (phase A8). */
final class ReferentielAdministratifAuthorization
{
    public static function peut(User $user, string $action): bool
    {
        return $user->can("structure.$action") || $user->can("administratif.$action");
    }
}
