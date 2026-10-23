<?php

namespace App\Services\Reporting;

/** Détermine si un rapport doit être généré via la file d'attente (P1-8). */
final class ReportingAsyncPolicy
{
    public static function doitAsync(string $type, string $format): bool
    {
        if (! config('reporting.async.enabled', true)) {
            return false;
        }

        if (in_array($type, config('reporting.async.types', []), true)) {
            return true;
        }

        return in_array($format, config('reporting.async.formats', []), true);
    }
}
