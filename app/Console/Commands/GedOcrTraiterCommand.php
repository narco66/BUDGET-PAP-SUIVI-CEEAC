<?php

namespace App\Console\Commands;

use App\Models\Ged\GedDocument;
use App\Services\Ged\GedOcrService;
use Illuminate\Console\Command;

/** Traite l'OCR sur les documents sans contenu indexé. */
class GedOcrTraiterCommand extends Command
{
    protected $signature = 'ged:ocr-traiter {--limit=50 : Nombre max de documents}';

    protected $description = 'Extrait le texte OCR des documents GED non indexés';

    public function handle(GedOcrService $ocr): int
    {
        $limit = (int) $this->option('limit');

        $documents = GedDocument::query()
            ->whereNull('contenu_ocr')
            ->where('est_archive', false)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $traites = 0;
        foreach ($documents as $document) {
            $texte = $ocr->extraire(
                $document->chemin_fichier,
                $document->mime_type ?? '',
                $document->extension ?? '',
            );

            if ($texte) {
                $document->update(['contenu_ocr' => $texte]);
                $document->numerisations()->latest()->first()?->update(['ocr_applique' => true]);
                $traites++;
            }
        }

        $this->info("OCR traité sur {$traites}/{$documents->count()} document(s).");

        return self::SUCCESS;
    }
}
