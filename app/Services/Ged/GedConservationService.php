<?php

namespace App\Services\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Models\Ged\GedConservationRule;
use App\Models\Ged\GedDocument;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GedConservationService
{
    public function __construct(
        private GedArchiveService $archives,
        private GedNotificationService $notifications,
    ) {}

    /**
     * @return array{analyses: int, archives: int}
     */
    public function appliquerRegles(bool $dryRun = false): array
    {
        $regles = GedConservationRule::where('actif', true)->with('typeDocument')->get();
        $analyses = 0;
        $archives = 0;

        $systemUser = User::role('administrateur')->where('actif', true)->first();

        foreach ($regles as $regle) {
            $query = GedDocument::query()
                ->where('est_archive', false)
                ->whereNotNull('date_expiration')
                ->whereDate('date_expiration', '<=', now());

            if ($regle->type_document_id) {
                $query->where('type_document_id', $regle->type_document_id);
            }

            foreach ($query->get() as $document) {
                $analyses++;

                if ($regle->sort_final === 'destruction_autorisee') {
                    if (! $dryRun) {
                        $document->update(['statut' => DocumentStatut::Expire]);
                    }
                    continue;
                }

                if ($regle->sort_final === 'archivage_definitif' && $systemUser) {
                    if (! $dryRun) {
                        $this->archives->archiver(
                            $document,
                            $systemUser,
                            'Archivage automatique — règle de conservation',
                            null,
                            'automatique',
                        );
                    }
                    $archives++;
                }

                if ($regle->alerte_jours_avant && $document->date_expiration) {
                    $jours = now()->diffInDays($document->date_expiration, false);
                    if ($jours >= 0 && $jours <= $regle->alerte_jours_avant) {
                        $this->notifications->expirationProche($document, (int) $jours);
                    }
                }
            }
        }

        Log::info('GED conservation', compact('analyses', 'archives', 'dryRun'));

        return compact('analyses', 'archives');
    }
}
