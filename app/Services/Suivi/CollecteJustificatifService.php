<?php

namespace App\Services\Suivi;

use App\Models\Collecte;
use RuntimeException;

/** Contrôle des justificatifs de réalisation (phase S1). */
class CollecteJustificatifService
{
    public function possedeJustificatif(Collecte $collecte): bool
    {
        $collecte->loadMissing(['pieces', 'gedDocuments']);

        return $collecte->pieces->isNotEmpty() || $collecte->gedDocuments->isNotEmpty();
    }

    public function assertPeutValider(Collecte $collecte): void
    {
        if (! config('suivi.justificatif_obligatoire', true)) {
            return;
        }

        if (! $this->possedeJustificatif($collecte)) {
            throw new RuntimeException(
                'Validation impossible : rattachez au moins un justificatif (GED ou pièce) à cette collecte.'
            );
        }
    }
}
