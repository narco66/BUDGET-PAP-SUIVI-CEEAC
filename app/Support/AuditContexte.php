<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Contexte de traçabilité partagé par les écritures du journal pendant une opération :
 * identifiant de requête, corrélation, workflow, tâche, titulaire représenté, motif.
 * Les contextes s'empilent ; les valeurs internes complètent les valeurs externes.
 */
class AuditContexte
{
    /** @var list<array<string, mixed>> */
    private static array $pile = [];

    private static ?string $requestId = null;

    /** Exécute $travail avec un contexte additionnel, retiré ensuite même en cas d'exception. */
    public static function pendant(array $valeurs, callable $travail): mixed
    {
        self::$pile[] = array_filter($valeurs, fn ($v) => $v !== null && $v !== '');
        try {
            return $travail();
        } finally {
            array_pop(self::$pile);
        }
    }

    /** @return array<string, mixed> contexte fusionné (le plus interne l'emporte) */
    public static function courant(): array
    {
        return array_merge(...(self::$pile ?: [[]]));
    }

    public static function definirRequete(?string $id): string
    {
        return self::$requestId = $id !== null && preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $id) ? $id : (string) Str::uuid();
    }

    public static function requete(): ?string
    {
        return self::$requestId;
    }

    /** Remise à zéro (fin de requête, tests, workers longue durée). */
    public static function reinitialiser(): void
    {
        self::$pile = [];
        self::$requestId = null;
    }
}
