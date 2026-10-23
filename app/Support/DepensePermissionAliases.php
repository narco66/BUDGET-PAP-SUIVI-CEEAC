<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Alias permissions chaîne de la dépense anglais → nomenclature FR (phase D0). */
final class DepensePermissionAliases
{
    /** @var array<string, string> */
    private const SIMPLE = [
        'depenses.engage' => 'engagement.creer',
        'depenses.liquidate' => 'liquidation.creer',
        'depenses.ordonnancer' => 'ordonnancement.creer',
        'depenses.pay' => 'paiement.creer',
        'depenses.admin' => 'depenses.admin',
    ];

    /** @var array<string, list<string>> */
    private const ANY = [
        'depenses.view' => ['besoin.voir', 'engagement.voir', 'liquidation.voir', 'ordonnancement.voir', 'paiement.voir'],
        'depenses.create' => ['besoin.creer', 'engagement.creer', 'liquidation.creer', 'ordonnancement.creer', 'paiement.creer'],
        'depenses.update' => ['besoin.modifier', 'engagement.modifier', 'liquidation.modifier', 'ordonnancement.modifier', 'paiement.modifier'],
        'depenses.validate' => ['besoin.valider', 'engagement.valider', 'liquidation.valider', 'ordonnancement.valider', 'paiement.valider'],
        'depenses.export' => ['besoin.exporter', 'engagement.exporter', 'liquidation.exporter', 'ordonnancement.exporter', 'paiement.exporter', 'rapport.exporter'],
    ];

    public static function concerne(string $ability): bool
    {
        return isset(self::SIMPLE[$ability]) || isset(self::ANY[$ability]);
    }

    /** @return bool|null true/false si alias dépense, null sinon */
    public static function evaluate(User $user, string $ability): ?bool
    {
        if (isset(self::SIMPLE[$ability])) {
            return self::possedePermission($user, self::SIMPLE[$ability]);
        }

        if (isset(self::ANY[$ability])) {
            foreach (self::ANY[$ability] as $permission) {
                if (self::possedePermission($user, $permission)) {
                    return true;
                }
            }

            return false;
        }

        return null;
    }

    private static function possedePermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public static function peut(User $user, string $abilityAnglais): bool
    {
        return self::evaluate($user, $abilityAnglais) === true;
    }
}
