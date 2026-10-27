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

    /** Échelle de priorité du référentiel (§ 4) : P1 critique … P4 faible. */
    public const PRIORITES = ['P1' => 'Critique', 'P2' => 'Élevée', 'P3' => 'Normale', 'P4' => 'Faible'];

    public static function priorite(?string $criticite): string
    {
        return match ($criticite) {
            self::URGENCE, self::CRITIQUE => 'P1',
            self::AVERTISSEMENT => 'P2',
            self::ATTENTION => 'P3',
            default => 'P4',
        };
    }

    /** @return list<string> criticités correspondant à une priorité */
    public static function deLaPriorite(string $priorite): array
    {
        return array_values(array_filter(self::tous(), fn (string $c) => self::priorite($c) === $priorite));
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
