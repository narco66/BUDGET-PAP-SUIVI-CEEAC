<?php

namespace App\Services\Reporting;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Throwable;

/** Génère des QR codes (data-URI PNG) embarquables dans les PDF/Word, via backend GD. */
class QrCodeService
{
    /** Retourne un data-URI PNG ("data:image/png;base64,…") ou null si la génération échoue. */
    public function dataUri(string $contenu, int $taille = 130): ?string
    {
        try {
            $result = (new Builder(
                writer: new PngWriter(),
                data: $contenu,
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: $taille,
                margin: 4,
            ))->build();

            return $result->getDataUri();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
