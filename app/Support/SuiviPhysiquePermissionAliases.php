<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Alias permissions suivi_physique.* → suivi.* (phase S4). */
final class SuiviPhysiquePermissionAliases
{
    /** @var array<string, string> */
    private const SIMPLE = [
        'suivi_physique.view' => 'suivi.voir',
        'suivi_physique.create' => 'suivi.creer',
        'suivi_physique.update' => 'suivi.modifier',
        'suivi_physique.validate' => 'suivi.valider',
        'suivi_physique.export' => 'suivi.exporter',
        'suivi_physique.admin' => 'suivi.valider',
    ];

    public static function concerne(string $ability): bool
    {
        return isset(self::SIMPLE[$ability]);
    }

    public static function evaluate(User $user, string $ability): ?bool
    {
        if (! isset(self::SIMPLE[$ability])) {
            return null;
        }

        return self::possedePermission($user, self::SIMPLE[$ability]);
    }

    private static function possedePermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
