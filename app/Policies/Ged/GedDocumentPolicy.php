<?php

namespace App\Policies\Ged;

use App\Models\Ged\GedDocument;
use App\Models\User;
use App\Services\Ged\GedAccessService;

class GedDocumentPolicy
{
    public function __construct(private GedAccessService $acces) {}

    public function viewAny(User $user): bool
    {
        return $user->can('ged.voir');
    }

    public function view(User $user, GedDocument $document): bool
    {
        return $this->acces->peutConsulter($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('ged.creer');
    }

    public function update(User $user, GedDocument $document): bool
    {
        return $user->can('ged.modifier') && $document->estModifiable()
            && ($document->created_by === $user->id || $user->can('ged.admin'));
    }

    public function delete(User $user, GedDocument $document): bool
    {
        return $user->can('ged.supprimer')
            && ($document->created_by === $user->id || $user->can('ged.admin'));
    }

    public function download(User $user, GedDocument $document): bool
    {
        return $this->acces->peutTelecharger($user, $document);
    }

    public function preview(User $user, GedDocument $document): bool
    {
        return $this->acces->peutConsulter($user, $document);
    }

    public function archive(User $user, GedDocument $document): bool
    {
        return $user->can('ged.archiver') && ! $document->est_archive;
    }

    public function restore(User $user, GedDocument $document): bool
    {
        return $user->can('ged.restaurer') && $document->est_archive;
    }

    public function share(User $user, GedDocument $document): bool
    {
        return $user->can('ged.partager') && $this->acces->peutConsulter($user, $document);
    }

    public function sign(User $user, GedDocument $document): bool
    {
        return $user->can('ged.signer') && ! $document->est_signe;
    }

    public function validate(User $user, GedDocument $document): bool
    {
        return $user->can('ged.valider');
    }

    public function reject(User $user, GedDocument $document): bool
    {
        return $user->can('ged.rejeter');
    }

    public function publish(User $user, GedDocument $document): bool
    {
        return $user->can('ged.publier');
    }

    public function export(User $user, ?GedDocument $document = null): bool
    {
        return $user->can('ged.exporter');
    }

    public function admin(User $user): bool
    {
        return $user->can('ged.admin');
    }
}
