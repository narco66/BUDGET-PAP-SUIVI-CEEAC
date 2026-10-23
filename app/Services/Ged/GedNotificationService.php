<?php

namespace App\Services\Ged;

use App\Models\Ged\GedDocument;
use App\Models\User;
use App\Notifications\Ged\AccesRefuseCritiqueNotification;
use App\Notifications\Ged\DocumentArchiveNotification;
use App\Notifications\Ged\DocumentDeposeNotification;
use App\Notifications\Ged\DocumentExpirationProcheNotification;
use App\Notifications\Ged\DocumentPartageNotification;
use App\Notifications\Ged\DocumentRejeteNotification;
use App\Notifications\Ged\DocumentSigneNotification;
use App\Notifications\Ged\DocumentSignatureDemandeeNotification;
use App\Notifications\Ged\DocumentValideNotification;
use App\Notifications\Ged\DocumentValidationDemandeeNotification;
use Illuminate\Support\Facades\Notification;

class GedNotificationService
{
    public function documentDepose(GedDocument $document, User $auteur): void
    {
        $destinataires = User::permission('ged.voir')
            ->where('actif', true)
            ->where('id', '!=', $auteur->id)
            ->limit(10)
            ->get();

        if ($destinataires->isNotEmpty()) {
            Notification::send($destinataires, new DocumentDeposeNotification($document));
        }
    }

    public function validationDemandee(GedDocument $document): void
    {
        $validateurs = User::permission('ged.valider')->where('actif', true)->get();
        if ($validateurs->isNotEmpty()) {
            Notification::send($validateurs, new DocumentValidationDemandeeNotification($document));
        }
    }

    public function documentValide(GedDocument $document): void
    {
        if ($document->createur?->actif) {
            $document->createur->notify(new DocumentValideNotification($document));
        }
    }

    public function documentRejete(GedDocument $document, ?string $motif = null): void
    {
        if ($document->createur?->actif) {
            $document->createur->notify(new DocumentRejeteNotification($document, $motif));
        }
    }

    public function signatureDemandee(GedDocument $document, $signature): void
    {
        $signature->loadMissing('signataire');
        $signataire = $signature->signataire ?? User::find($signature->signataire_id);
        if ($signataire?->actif) {
            $signataire->notify(new DocumentSignatureDemandeeNotification($document, $signature));
        }
    }

    public function documentSigne(GedDocument $document): void
    {
        $destinataires = collect([$document->createur])->filter(fn ($u) => $u?->actif);
        if ($destinataires->isNotEmpty()) {
            Notification::send($destinataires, new DocumentSigneNotification($document));
        }
    }

    public function documentArchive(GedDocument $document, string $motif): void
    {
        if ($document->createur?->actif) {
            $document->createur->notify(new DocumentArchiveNotification($document, $motif));
        }
    }

    public function documentPartage(GedDocument $document, User $destinataire, User $partagePar): void
    {
        if ($destinataire->actif) {
            $destinataire->notify(new DocumentPartageNotification($document, $partagePar->name));
        }
    }

    public function expirationProche(GedDocument $document, int $joursRestants): void
    {
        if ($document->createur?->actif) {
            $document->createur->notify(new DocumentExpirationProcheNotification($document, $joursRestants));
        }
    }

    public function accesRefuseCritique(GedDocument $document, User $utilisateur, string $action): void
    {
        if (! in_array($document->niveau_confidentialite?->value ?? '', ['confidentiel', 'tres_confidentiel'], true)) {
            return;
        }

        $admins = User::permission('ged.admin')->where('actif', true)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new AccesRefuseCritiqueNotification($document, $utilisateur->name, $action));
        }
    }
}
