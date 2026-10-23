<?php

namespace App\Enums\Ged;

enum DocumentStatut: string
{
    case Brouillon = 'brouillon';
    case Depose = 'depose';
    case EnValidation = 'en_validation';
    case Valide = 'valide';
    case Rejete = 'rejete';
    case Signe = 'signe';
    case Publie = 'publie';
    case Archive = 'archive';
    case Expire = 'expire';
    case Supprime = 'supprime';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Depose => 'Déposé',
            self::EnValidation => 'En cours de validation',
            self::Valide => 'Validé',
            self::Rejete => 'Rejeté',
            self::Signe => 'Signé',
            self::Publie => 'Publié',
            self::Archive => 'Archivé',
            self::Expire => 'Expiré',
            self::Supprime => 'Supprimé logiquement',
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
