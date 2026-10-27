<?php

namespace App\Http\Controllers\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Enums\Ged\NiveauConfidentialite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ged\GedDocumentRequest;
use App\Http\Requests\Ged\GedDocumentVersionRequest;
use App\Http\Requests\Ged\GedMotifRequest;
use App\Models\Exercice;
use App\Models\Ged\GedCategorie;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedDossier;
use App\Models\Ged\GedRechercheSauvegardee;
use App\Models\Ged\GedTypeDocument;
use App\Services\Ged\GedAccessService;
use App\Services\Ged\GedArchiveService;
use App\Services\Ged\GedDocumentService;
use App\Services\Ged\GedJournalService;
use App\Services\Ged\GedSearchService;
use App\Services\Ged\GedSignatureService;
use App\Services\Ged\GedStorageService;
use App\Services\Ged\GedWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GedDocumentController extends Controller
{
    public function __construct(
        private GedDocumentService $documents,
        private GedSearchService $recherche,
        private GedAccessService $acces,
        private GedJournalService $journal,
        private GedStorageService $storage,
        private GedArchiveService $archives,
        private GedSignatureService $signatures,
        private GedWorkflowService $workflow,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GedDocument::class);

        $filtres = $request->all();

        return view('ged.documents.index', [
            'documents' => $this->recherche->rechercher($filtres, $request->user()),
            'indicateurs' => $this->recherche->indicateurs($filtres, $request->user()),
            'filtres' => $filtres,
            'statuts' => DocumentStatut::options(),
            'niveaux' => NiveauConfidentialite::options(),
        ] + $this->recherche->options($request->user()));
    }

    public function create(): View
    {
        $this->authorize('create', GedDocument::class);

        return view('ged.documents.create', $this->formulaireDonnees());
    }

    public function store(GedDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', GedDocument::class);

        $request->validated();

        $donnees = $request->only([
            'titre', 'description', 'categorie_id', 'type_document_id', 'dossier_id',
            'module_source', 'niveau_confidentialite', 'date_document', 'date_expiration',
        ]);

        if ($request->filled('tags')) {
            $donnees['tags'] = array_map('trim', explode(',', $request->input('tags')));
        }

        $document = $this->documents->deposer($request->file('fichier'), $donnees, $request->user());

        return redirect()->route('ged.documents.show', $document)
            ->with('succes', 'Document déposé avec succès.');
    }

    public function show(GedDocument $document): View
    {
        $this->authorize('view', $document);

        $this->journal->enregistrer('consultation', $document, auth()->user());

        $document->load([
            'categorie', 'typeDocument', 'dossier', 'createur', 'versions.auteur',
            'signatures.signataire', 'validations.validateur', 'tags', 'journaux.utilisateur',
        ]);

        return view('ged.documents.show', [
            'document' => $document,
            'verification' => $this->signatures->verifier($document),
        ]);
    }

    public function edit(GedDocument $document): View
    {
        $this->authorize('update', $document);

        return view('ged.documents.edit', array_merge(
            ['document' => $document],
            $this->formulaireDonnees(),
        ));
    }

    public function update(GedDocumentRequest $request, GedDocument $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $request->validated();

        $donnees = $request->only([
            'titre', 'description', 'categorie_id', 'type_document_id', 'dossier_id',
            'niveau_confidentialite', 'date_document', 'date_expiration',
        ]);

        if ($request->filled('tags')) {
            $donnees['tags'] = array_map('trim', explode(',', $request->input('tags')));
        }

        $this->documents->mettreAJour($document, $donnees, $request->user());

        return redirect()->route('ged.documents.show', $document)
            ->with('succes', 'Document mis à jour.');
    }

    public function destroy(Request $request, GedDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->workflow->supprimerLogiquement($document, $request->user(), $request->input('motif'));

        return redirect()->route('ged.documents.index')
            ->with('succes', 'Document déplacé en corbeille.');
    }

    public function download(GedDocument $document): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless($this->storage->existe($document->chemin_fichier), 404);

        $this->journal->enregistrer('telechargement', $document, auth()->user());

        return Storage::disk('local')->download($document->chemin_fichier, $document->nom_original);
    }

    public function preview(GedDocument $document): StreamedResponse
    {
        $this->authorize('preview', $document);

        abort_unless($this->storage->existe($document->chemin_fichier), 404);

        $this->journal->enregistrer('previsualisation', $document, auth()->user());

        return Storage::disk('local')->response($document->chemin_fichier, $document->nom_original, [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
        ]);
    }

    public function version(GedDocumentVersionRequest $request, GedDocument $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $request->validated();

        $this->documents->nouvelleVersion($document, $request->file('fichier'), $request->user(), $request->input('commentaire'));

        return back()->with('succes', 'Nouvelle version enregistrée.');
    }

    public function archiver(GedMotifRequest $request, GedDocument $document): RedirectResponse
    {
        $this->authorize('archive', $document);

        $data = $request->validated();

        $this->archives->archiver($document, $request->user(), $data['motif'], $data['commentaire'] ?? null);

        return back()->with('succes', 'Document archivé.');
    }

    public function restaurer(GedMotifRequest $request, GedDocument $document): RedirectResponse
    {
        $this->authorize('restore', $document);

        $data = $request->validated();

        $this->archives->restaurer($document, $request->user(), $data['motif']);

        return back()->with('succes', 'Document restauré.');
    }

    public function rechercher(Request $request): View
    {
        abort_unless($request->user()->can('ged.rechercher') || $request->user()->can('ged.voir'), 403);

        return view('ged.recherche', [
            'documents' => $this->recherche->rechercher($request->all(), $request->user()),
            'filtres' => $request->all(),
            'statuts' => DocumentStatut::options(),
            'niveaux' => NiveauConfidentialite::options(),
            'categories' => GedCategorie::where('actif', true)->orderBy('libelle')->get(),
            'types' => GedTypeDocument::where('actif', true)->orderBy('libelle')->get(),
            'dossiers' => GedDossier::orderBy('titre')->get(),
            'recherchesSauvegardees' => GedRechercheSauvegardee::where('utilisateur_id', $request->user()->id)
                ->orderBy('nom')->get(),
        ]);
    }

    public function archives(Request $request): View
    {
        $request->merge(['est_archive' => 1]);

        return view('ged.archives.index', [
            'documents' => $this->recherche->rechercher($request->all(), $request->user()),
        ]);
    }

    public function confidentiels(Request $request): View
    {
        $request->merge(['niveau_confidentialite' => 'confidentiel']);

        return view('ged.documents.confidentiels', [
            'documents' => $this->recherche->rechercher($request->all(), $request->user()),
        ]);
    }

    public function expires(Request $request): View
    {
        $request->merge(['statut' => 'expire']);

        return view('ged.documents.expires', [
            'documents' => $this->recherche->rechercher($request->all(), $request->user()),
        ]);
    }

    public function partages(Request $request): View
    {
        abort_unless($request->user()->can('ged.voir'), 403);

        $documents = GedDocument::whereHas('partages', function ($q) use ($request) {
            $q->where('partage_avec', $request->user()->id)
                ->orWhere('structure_id', $request->user()->structure_id);
        })->with(['categorie', 'typeDocument', 'dossier', 'createur', 'tags'])->latest('date_depot')->paginate(20);
        app(\App\Services\Ged\GedAccessService::class)->prechargerPartages($documents->getCollection(), $request->user());

        return view('ged.partages.index', compact('documents'));
    }

    /** @return array<string, mixed> */
    private function formulaireDonnees(): array
    {
        return [
            'categories' => GedCategorie::where('actif', true)->orderBy('libelle')->get(),
            'types' => GedTypeDocument::where('actif', true)->orderBy('libelle')->get(),
            'dossiers' => GedDossier::orderBy('titre')->get(),
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'niveaux' => NiveauConfidentialite::options(),
            'statuts' => DocumentStatut::options(),
        ];
    }
}
