<?php



namespace App\Support;



use App\Models\User;

use Spatie\Permission\Exceptions\PermissionDoesNotExist;



/** Alias permissions suivi_evaluation.* → suivi.* / recommandation.* (phase P0). */

final class SuiviEvaluationPermissionAliases

{

    /** @var array<string, string> */

    private const SIMPLE = [

        'suivi_evaluation.view' => 'suivi.voir',

        'suivi_evaluation.create' => 'suivi.creer',

        'suivi_evaluation.update' => 'suivi.modifier',

        'suivi_evaluation.validate' => 'suivi.valider',

        'suivi_evaluation.recommend' => 'recommandation.creer',

        'suivi_evaluation.export' => 'suivi.exporter',

        'suivi_evaluation.admin' => 'suivi.valider',

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

