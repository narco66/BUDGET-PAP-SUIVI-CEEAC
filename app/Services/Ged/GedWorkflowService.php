<?php

namespace App\Services\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Models\Ged\GedCorbeille;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedValidation;
use App\Models\Ged\GedWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GedWorkflowService
{
    public function __construct(
        private GedJournalService $journal,
        private GedNotificationService $notifications,
    ) {}

    public function demarrer(GedDocument $document, GedWorkflow $workflow, User $user): void
    {
        $document->update(['statut' => DocumentStatut::EnValidation]);

        foreach ($workflow->etapes as $etape) {
            GedValidation::create([
                'document_id' => $document->id,
                'workflow_etape_id' => $etape->id,
                'validateur_id' => $etape->utilisateur_id ?? $user->id,
                'statut' => 'en_attente',
                'delai_jours' => $etape->delai_jours,
            ]);
        }

        $this->journal->enregistrer('workflow_demarre', $document, $user, null, ['workflow' => $workflow->code]);
        $this->notifications->validationDemandee($document);
    }

    public function valider(GedValidation $validation, User $user, ?string $commentaire = null): void
    {
        $validation->update([
            'statut' => 'valide',
            'commentaire' => $commentaire,
            'date_action' => now(),
        ]);

        $document = $validation->document;
        $enAttente = $document->validations()->where('statut', 'en_attente')->exists();

        if (! $enAttente) {
            $document->update(['statut' => DocumentStatut::Valide, 'updated_by' => $user->id]);
        }

        $this->journal->enregistrer('validation', $document, $user, null, ['etape' => $validation->workflow_etape_id]);
        $this->notifications->documentValide($document);
    }

    public function rejeter(GedValidation $validation, User $user, string $motif): void
    {
        $validation->update([
            'statut' => 'rejete',
            'motif_rejet' => $motif,
            'date_action' => now(),
        ]);

        $validation->document->update([
            'statut' => DocumentStatut::Rejete,
            'updated_by' => $user->id,
        ]);

        $this->journal->enregistrer('rejet', $validation->document, $user, null, ['motif' => $motif]);
        $this->notifications->documentRejete($validation->document, $motif);
    }

    public function supprimerLogiquement(GedDocument $document, User $user, ?string $motif = null): void
    {
        DB::transaction(function () use ($document, $user, $motif) {
            GedCorbeille::create([
                'document_id' => $document->id,
                'supprime_par' => $user->id,
                'motif' => $motif,
                'date_suppression' => now(),
                'date_expiration_corbeille' => now()->addDays(30),
            ]);

            $document->update([
                'statut' => DocumentStatut::Supprime,
                'deleted_by' => $user->id,
            ]);

            $document->delete();
        });

        $this->journal->enregistrer('suppression', $document, $user, null, ['motif' => $motif]);
    }
}
