<?php

namespace App\Enums\Ged;

enum NiveauConfidentialite: string
{
    case Public = 'public';
    case Interne = 'interne';
    case Restreint = 'restreint';
    case Confidentiel = 'confidentiel';
    case TresConfidentiel = 'tres_confidentiel';

    public function libelle(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Interne => 'Interne',
            self::Restreint => 'Restreint',
            self::Confidentiel => 'Confidentiel',
            self::TresConfidentiel => 'Très confidentiel',
        };
    }

    public function niveau(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Interne => 1,
            self::Restreint => 2,
            self::Confidentiel => 3,
            self::TresConfidentiel => 4,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $opts = [];
        foreach (self::cases() as $case) {
            $opts[$case->value] = $case->libelle();
        }

        return $opts;
    }
}
