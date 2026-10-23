<?php

namespace App\Support;

use App\Models\User;

/** Alias permissions PAP anglais → nomenclature FR (phase P5). */
final class PapPermissionAliases
{
    /** @var array<string, string> */
    public const MAP = [
        'pap.view' => 'pap.voir',
        'pap.create' => 'pap.creer',
        'pap.update' => 'pap.modifier',
        'pap.delete' => 'pap.supprimer',
        'pap.export' => 'pap.exporter',
        'pap.validate' => 'pap.valider',
        'pap.consolidate' => 'pap.modifier',
        'pap.revise' => 'pap.modifier',
        'pap.admin' => 'pap.modifier',
    ];

    public static function resolve(string $ability): ?string
    {
        return self::MAP[$ability] ?? null;
    }

    public static function peut(User $user, string $abilityAnglais): bool
    {
        $fr = self::resolve($abilityAnglais);

        return $fr !== null && $user->can($fr);
    }
}
