<?php

namespace App\Services\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Models\Ged\GedArchive;
use App\Models\Ged\GedDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GedArchiveService
{
    public function __construct(
        private GedJournalService $journal,
        private GedNotificationService $notifications,
    ) {}

    public function archiver(GedDocument $document, User $user, string $motif, ?string $commentaire = null, string $type = 'manuel'): GedArchive
    {
        abort_if($document->est_archive, 422, 'Document déjà archivé.');

        $archive = DB::transaction(function () use ($document, $user, $motif, $commentaire, $type) {
            $document->update([
                'est_archive' => true,
                'est_verrouille' => true,
                'statut' => DocumentStatut::Archive,
                'date_archivage' => now(),
                'archived_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return GedArchive::create([
                'document_id' => $document->id,
                'motif' => $motif,
                'commentaire' => $commentaire,
                'type_archivage' => $type,
                'date_archivage' => now(),
                'archived_by' => $user->id,
            ]);
        });

        $this->journal->enregistrer('archivage', $document, $user, null, ['motif' => $motif]);
        $this->notifications->documentArchive($document, $motif);

        return $archive;
    }

    public function restaurer(GedDocument $document, User $user, string $motif): GedDocument
    {
        abort_unless($document->est_archive, 422, 'Document non archivé.');

        DB::transaction(function () use ($document, $user, $motif) {
            $derniere = $document->archives()->where('est_restaure', false)->latest()->first();

            $document->update([
                'est_archive' => false,
                'est_verrouille' => false,
                'statut' => DocumentStatut::Depose,
                'date_archivage' => null,
                'archived_by' => null,
                'updated_by' => $user->id,
            ]);

            $derniere?->update([
                'est_restaure' => true,
                'date_restauration' => now(),
                'restored_by' => $user->id,
                'motif_restauration' => $motif,
            ]);
        });

        $this->journal->enregistrer('restauration', $document, $user, null, ['motif' => $motif]);

        return $document->fresh();
    }
}
