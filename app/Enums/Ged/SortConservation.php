<?php

namespace App\Enums\Ged;

enum SortConservation: string
{
    case ConservationPermanente = 'conservation_permanente';
    case ArchivageDefinitif = 'archivage_definitif';
    case DestructionAutorisee = 'destruction_autorisee';

    public function libelle(): string
    {
        return match ($this) {
            self::ConservationPermanente => 'Conservation permanente',
            self::ArchivageDefinitif => 'Archivage définitif',
            self::DestructionAutorisee => 'Destruction autorisée',
        };
    }
}
