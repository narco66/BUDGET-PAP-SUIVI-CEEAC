<?php

namespace App\Services\Ged;

use App\Models\Ged\GedDocument;
use App\Models\Ged\GedJournal;
use App\Models\User;
use Illuminate\Http\Request;

class GedJournalService
{
    public function enregistrer(
        string $action,
        ?GedDocument $document = null,
        ?User $utilisateur = null,
        ?array $ancienne = null,
        ?array $nouvelle = null,
        ?string $commentaire = null,
        ?Request $request = null,
    ): GedJournal {
        $req = $request ?? request();

        return GedJournal::create([
            'document_id' => $document?->id,
            'utilisateur_id' => $utilisateur?->id ?? auth()->id(),
            'action' => $action,
            'adresse_ip' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'ancienne_valeur' => $ancienne,
            'nouvelle_valeur' => $nouvelle,
            'commentaire' => $commentaire,
            'module_source' => $document?->module_source,
        ]);
    }
}
