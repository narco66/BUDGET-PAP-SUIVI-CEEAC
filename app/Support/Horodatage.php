<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Affichage des instants (stockés en UTC) dans le fuseau institutionnel, sans altérer l'instant (§ 5.1). */
final class Horodatage
{
    public static function fuseau(): string
    {
        return (string) config('app.timezone_affichage', 'Africa/Libreville');
    }

    public static function local(DateTimeInterface|string|null $instant, string $format = 'd/m/Y H:i:s'): string
    {
        if ($instant === null || $instant === '') {
            return '—';
        }
        $c = $instant instanceof DateTimeInterface ? CarbonImmutable::instance($instant) : CarbonImmutable::parse($instant, 'UTC');

        return $c->setTimezone(self::fuseau())->format($format);
    }

    /** Instant UTC ISO 8601 à la microseconde (ex. 2026-10-25T08:42:18.325000Z). */
    public static function utc(DateTimeInterface|string|null $instant): ?string
    {
        return AuditEmpreinte::instant($instant);
    }
}
