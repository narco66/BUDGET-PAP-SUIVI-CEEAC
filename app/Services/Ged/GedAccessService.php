<?php

namespace App\Services\Ged;

use App\Enums\Ged\NiveauConfidentialite;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedPartage;
use App\Models\User;
use App\Services\AccessScopeService;

class GedAccessService
{
    public function __construct(
        private GedJournalService $journal,
        private GedNotificationService $notifications,
        private AccessScopeService $scopes,
    ) {}

    public function peutConsulter(User $user, GedDocument $document): bool
    {
        if ($user->can('ged.admin')) {
            return true;
        }

        if ($document->created_by === $user->id) {
            return true;
        }

        if (! $user->can('ged.voir')) {
            $this->journalRefus($user, $document, 'consultation');

            return false;
        }

        if (! $this->niveauAutorise($user, $document)) {
            $this->journalRefus($user, $document, 'consultation_confidentialite');

            return false;
        }

        if (! $this->scopes->dansPerimetre($user, $document->structure_id)) {
            $this->journalRefus($user, $document, 'consultation_perimetre');

            return false;
        }

        if ($this->partageActif($user, $document)) {
            return true;
        }

        return $document->niveau_confidentialite !== NiveauConfidentialite::TresConfidentiel;
    }

    public function peutTelecharger(User $user, GedDocument $document): bool
    {
        if (! $this->peutConsulter($user, $document)) {
            return false;
        }

        if ($user->can('ged.admin') || $document->created_by === $user->id) {
            return true;
        }

        if (! $user->can('ged.telecharger') && ! $user->can('ged.exporter')) {
            return false;
        }

        $partage = GedPartage::where('document_id', $document->id)
            ->where(function ($q) use ($user) {
                $q->where('partage_avec', $user->id)
                    ->orWhere('structure_id', $user->structure_id);
            })
            ->where('peut_telecharger', true)
            ->where(fn ($q) => $q->whereNull('expire_le')->orWhere('expire_le', '>', now()))
            ->exists();

        return $partage || $document->niveau_confidentialite === NiveauConfidentialite::Public;
    }

    private function niveauAutorise(User $user, GedDocument $document): bool
    {
        $niveauDoc = $document->niveau_confidentialite->niveau();

        if ($niveauDoc <= NiveauConfidentialite::Interne->niveau()) {
            return true;
        }

        if ($user->can('ged.admin')) {
            return true;
        }

        return $niveauDoc <= NiveauConfidentialite::Restreint->niveau();
    }

    private function partageActif(User $user, GedDocument $document): bool
    {
        return GedPartage::where('document_id', $document->id)
            ->where(function ($q) use ($user) {
                $q->where('partage_avec', $user->id)
                    ->orWhere('structure_id', $user->structure_id);
            })
            ->where(fn ($q) => $q->whereNull('expire_le')->orWhere('expire_le', '>', now()))
            ->exists();
    }

    private function journalRefus(User $user, GedDocument $document, string $action): void
    {
        $this->journal->enregistrer('acces_refuse', $document, $user, null, null, $action);
        $this->notifications->accesRefuseCritique($document, $user, $action);
    }
}
