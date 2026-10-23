<?php

namespace App\Enums\Ged;

/** Niveaux de signature électronique (eIDAS art. 26–28). */
enum SignatureNiveau: string
{
    case Interne = 'interne';
    case Avancee = 'avancee';
    case Qualifiee = 'qualifiee';

    public function libelle(): string
    {
        return match ($this) {
            self::Interne => 'Interne (CEEAC)',
            self::Avancee => 'Avancée',
            self::Qualifiee => 'Qualifiée (eIDAS)',
        };
    }

    public function estQualifiee(): bool
    {
        return $this === self::Qualifiee;
    }
}
