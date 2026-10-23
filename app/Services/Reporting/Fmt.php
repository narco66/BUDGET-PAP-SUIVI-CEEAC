<?php

namespace App\Services\Reporting;

/** Formatage homogène des valeurs de rapport (utilisé par les rendus PDF et Word). */
class Fmt
{
    public static function valeur(mixed $v, string $fmt): string
    {
        if ($v === null || $v === '') {
            return $fmt === 'text' ? (string) $v : '—';
        }

        return match ($fmt) {
            'money' => number_format((float) $v, 0, ',', ' '),
            'percent' => number_format((float) $v, 1, ',', ' ').' %',
            'num' => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ','),
            'int' => number_format((float) $v, 0, ',', ' '),
            default => (string) $v,
        };
    }
}
