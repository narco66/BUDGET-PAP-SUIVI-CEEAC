<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Alias permissions reporting.* → rapport.* (phase P0). */
final class ReportingPermissionAliases
{
    /** @var array<string, string> */
    private const SIMPLE = [
        'reporting.view' => 'rapport.voir',
        'reporting.create' => 'rapport.creer',
        'reporting.generate' => 'rapport.exporter',
        'reporting.validate' => 'rapport.valider',
        'reporting.publish' => 'rapport.exporter',
        'reporting.export' => 'rapport.exporter',
        'reporting.admin' => 'rapport.supprimer',
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
