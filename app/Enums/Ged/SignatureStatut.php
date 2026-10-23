<?php

namespace App\Enums\Ged;

enum SignatureStatut: string
{
    case NonRequis = 'non_requis';
    case EnAttente = 'en_attente';
    case Partielle = 'partielle';
    case Signe = 'signe';
    case Rejete = 'rejete';

    public function libelle(): string
    {
        return match ($this) {
            self::NonRequis => 'Non requis',
            self::EnAttente => 'En attente',
            self::Partielle => 'Partielle',
            self::Signe => 'Signé',
            self::Rejete => 'Rejeté',
        };
    }
}
