<?php

namespace App\Support;

/**
 * Représentation API des montants : chaîne décimale à point,
 * pour éviter les erreurs d'arrondi IEEE-754 côté client.
 */
final class Montant
{
    public static function api(mixed $valeur, int $decimales = 2): string
    {
        if ($valeur === null || $valeur === '') {
            return number_format(0, $decimales, '.', '');
        }

        return number_format((float) $valeur, $decimales, '.', '');
    }

    /** @param  array<string, mixed>  $ligne */
    public static function champs(array $ligne, array $cles, int $decimales = 2): array
    {
        foreach ($cles as $cle) {
            if (array_key_exists($cle, $ligne)) {
                $ligne[$cle] = self::api($ligne[$cle], $decimales);
            }
        }

        return $ligne;
    }
}
