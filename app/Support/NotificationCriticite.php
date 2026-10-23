<?php

namespace App\Support;

/** Niveaux de criticité institutionnels (module Notifications & Alertes). */
final class NotificationCriticite
{
    public const INFORMATION = 'information';

    public const ATTENTION = 'attention';

    public const AVERTISSEMENT = 'avertissement';

    public const CRITIQUE = 'critique';

    public const URGENCE = 'urgence';

    /** @return list<string> */
    public static function tous(): array
    {
        return [
            self::INFORMATION,
            self::ATTENTION,
            self::AVERTISSEMENT,
            self::CRITIQUE,
            self::URGENCE,
        ];
    }

    public static function badgeClass(string $criticite): string
    {
        return match ($criticite) {
            self::URGENCE, self::CRITIQUE => 'danger',
            self::AVERTISSEMENT => 'warning',
            self::ATTENTION => 'info',
            default => 'secondary',
        };
    }
}
