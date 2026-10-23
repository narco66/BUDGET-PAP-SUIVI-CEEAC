<?php

namespace App\Services\Ged;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/** Extraction de texte pour recherche OCR (basique ou Tesseract). */
class GedOcrService
{
    public function extraire(string $cheminRelatif, string $mimeType, string $extension): ?string
    {
        if (! config('ged.ocr.active', false)) {
            return $this->extractionBasique($cheminRelatif, $extension);
        }

        return match (config('ged.ocr.driver', 'basique')) {
            'tesseract' => $this->tesseract($cheminRelatif, $extension),
            default => $this->extractionBasique($cheminRelatif, $extension),
        };
    }

    private function extractionBasique(string $cheminRelatif, string $extension): ?string
    {
        $ext = strtolower($extension);

        if (in_array($ext, ['txt', 'csv'], true)) {
            return Storage::disk('local')->get($cheminRelatif);
        }

        return null;
    }

    private function tesseract(string $cheminRelatif, string $extension): ?string
    {
        $absolu = Storage::disk('local')->path($cheminRelatif);
        $ext = strtolower($extension);

        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'tiff'], true)) {
            return $this->extractionBasique($cheminRelatif, $extension);
        }

        $tesseract = config('ged.ocr.tesseract_path', 'tesseract');
        $process = new Process([$tesseract, $absolu, 'stdout', '-l', 'fra']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }
}
