<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Ged\GedDocument;
use App\Models\User;
use App\Services\Ged\GedAttachmentService;
use App\Services\Ged\GedDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GedAttachmentController extends Controller
{
    public function __construct(
        private GedAttachmentService $attachments,
        private GedDocumentService $documents,
    ) {}

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('ged.creer'), 403);

        $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,csv'],
            'titre' => ['nullable', 'string', 'max:255'],
        ]);

        $fichier = $request->file('fichier');
        $titre = $request->filled('titre')
            ? $request->input('titre')
            : $fichier->getClientOriginalName();

        $this->attachments->rattacherDepuisModule($type, $id, $fichier, ['titre' => $titre], $request->user(), $this->documents);

        return back()->with('succes', 'Document GED rattaché au dossier métier.');
    }

    public function partager(Request $request, GedDocument $document): RedirectResponse
    {
        $this->authorize('share', $document);

        $request->validate([
            'utilisateur_id' => ['required', 'exists:users,id'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $destinataire = User::findOrFail($request->input('utilisateur_id'));
        $this->attachments->partager(
            $document,
            $request->user(),
            $destinataire,
            app(\App\Services\Ged\GedNotificationService::class),
            true,
            $request->input('commentaire'),
        );

        return back()->with('succes', 'Document partagé.');
    }
}
