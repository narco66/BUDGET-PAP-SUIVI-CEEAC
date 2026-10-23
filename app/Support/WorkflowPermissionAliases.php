<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Alias workflow.* → permissions métier existantes (phase W-P0). */
final class WorkflowPermissionAliases
{
    public static function concerne(string $ability): bool
    {
        return str_starts_with($ability, 'workflow.');
    }

    public static function evaluate(User $user, string $ability): ?bool
    {
        if (! self::concerne($ability)) {
            return null;
        }

        return match ($ability) {
            'workflow.view' => self::auMoinsUne($user, [
                'pap.voir', 'budget.voir', 'besoin.voir', 'engagement.voir',
                'recette.voir', 'ged.voir',
            ]),
            'workflow.validate' => self::auMoinsUne($user, [
                'pap.valider', 'budget.valider', 'besoin.valider', 'engagement.valider',
                'liquidation.valider', 'ordonnancement.valider', 'paiement.valider', 'recette.valider',
            ]),
            'workflow.reject' => self::auMoinsUne($user, [
                'pap.valider', 'budget.valider', 'besoin.valider', 'engagement.rejeter',
                'liquidation.rejeter', 'ged.rejeter',
            ]),
            'workflow.create', 'workflow.update', 'workflow.admin' => $user->hasRole('administrateur'),
            'workflow.audit' => self::auMoinsUne($user, ['audit.voir']) || $user->hasRole('auditeur'),
            default => null,
        };
    }

    /** @param list<string> $permissions */
    private static function auMoinsUne(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        return false;
    }
}
