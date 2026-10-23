<?php

namespace App\Services\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Models\Ged\GedDocument;
use App\Models\Rapport;
use App\Models\User;
use Illuminate\Support\Str;

/** Pont non destructif Reporting ↔ GED (indexation des rapports générés). */
class GedRapportBridgeService
{
    public function __construct(
        private GedReferenceService $references,
        private GedJournalService $journal,
    ) {}

    public function dejaIndexe(Rapport $rapport): bool
    {
        return GedDocument::where('module_source', 'REPORTING')
            ->where('module_source_id', $rapport->id)
            ->exists();
    }

    public function indexer(Rapport $rapport, User $user): GedDocument
    {
        if ($this->dejaIndexe($rapport)) {
            return GedDocument::where('module_source', 'REPORTING')
                ->where('module_source_id', $rapport->id)
                ->firstOrFail();
        }

        $ext = match ($rapport->format) {
            'pdf' => 'pdf',
            'excel' => 'xlsx',
            'word' => 'docx',
            'csv' => 'csv',
            'pptx' => 'pptx',
            default => 'bin',
        };

        $document = GedDocument::create([
            'uuid' => (string) Str::uuid(),
            'reference' => $this->references->generer('REPORTING', strtoupper($rapport->type)),
            'titre' => $rapport->titre,
            'description' => "Rapport {$rapport->reference} v{$rapport->version} indexé depuis Reporting",
            'module_source' => 'REPORTING',
            'module_source_id' => $rapport->id,
            'attachable_type' => Rapport::class,
            'attachable_id' => $rapport->id,
            'chemin_fichier' => $rapport->chemin,
            'nom_original' => "{$rapport->reference}.{$ext}",
            'extension' => $ext,
            'mime_type' => $this->mimePour($rapport->format),
            'taille' => $rapport->taille,
            'hash_sha256' => $rapport->hash,
            'version_courante' => $rapport->version,
            'statut' => DocumentStatut::Depose,
            'niveau_confidentialite' => 'interne',
            'date_document' => now()->toDateString(),
            'date_depot' => now(),
            'exercice_id' => $rapport->exercice_id,
            'est_signe' => (bool) $rapport->signe_par,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'metadonnees_metier' => [
                'rapport_reference' => $rapport->reference,
                'rapport_type' => $rapport->type,
                'rapport_format' => $rapport->format,
                'qr_token' => $rapport->qr_token,
            ],
        ]);

        $this->journal->enregistrer('indexation_reporting', $document, $user, null, [
            'rapport_id' => $rapport->id,
        ]);

        return $document;
    }

    private function mimePour(string $format): string
    {
        return match ($format) {
            'pdf' => 'application/pdf',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'word' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'csv' => 'text/csv',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
    }
}
