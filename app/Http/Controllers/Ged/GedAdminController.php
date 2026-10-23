<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Ged\GedCategorie;
use App\Models\Ged\GedConservationRule;
use App\Models\Ged\GedDossier;
use App\Models\Ged\GedJournal;
use App\Models\Ged\GedSignature;
use App\Models\Ged\GedTypeDocument;
use App\Models\Ged\GedValidation;
use App\Models\Ged\GedVersion;
use App\Models\Ged\GedWorkflow;
use App\Models\User;
use App\Services\Ged\GedSignatureService;
use App\Services\Ged\GedWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GedAdminController extends Controller
{
    public function __construct(
        private GedSignatureService $signatures,
        private GedWorkflowService $workflow,
    ) {}

    public function categories(): View
    {
        abort_unless(auth()->user()->can('ged.admin'), 403);

        return view('ged.admin.categories', [
            'categories' => GedCategorie::with('parent')->orderBy('ordre')->paginate(20),
        ]);
    }

    public function storeCategorie(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('ged.admin'), 403);

        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:ged_categories,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:ged_categories,id'],
        ]);

        GedCategorie::create([
            ...$request->only(['code', 'libelle', 'description', 'parent_id']),
            'created_by' => auth()->id(),
        ]);

        return back()->with('succes', 'Catégorie créée.');
    }

    public function types(): View
    {
        abort_unless(auth()->user()->can('ged.admin'), 403);

        return view('ged.admin.types', [
            'types' => GedTypeDocument::with('categorie')->paginate(20),
            'categories' => GedCategorie::where('actif', true)->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('ged.admin'), 403);

        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:ged_types_documents,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'categorie_id' => ['nullable', 'exists:ged_categories,id'],
        ]);

        GedTypeDocument::create([
            ...$request->only(['code', 'libelle', 'description', 'categorie_id']),
            'created_by' => auth()->id(),
        ]);

        return back()->with('succes', 'Type de document créé.');
    }

    public function dossiers(): View
    {
        abort_unless(auth()->user()->can('ged.voir'), 403);

        return view('ged.dossiers.index', [
            'dossiers' => GedDossier::with(['categorie', 'structure'])->paginate(20),
        ]);
    }

    public function storeDossier(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('ged.creer'), 403);

        $request->validate([
            'reference' => ['required', 'string', 'max:100', 'unique:ged_dossiers,reference'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'categorie_id' => ['nullable', 'exists:ged_categories,id'],
            'parent_id' => ['nullable', 'exists:ged_dossiers,id'],
        ]);

        GedDossier::create([
            ...$request->only(['reference', 'titre', 'description', 'categorie_id', 'parent_id', 'module_source']),
            'created_by' => auth()->id(),
        ]);

        return back()->with('succes', 'Dossier créé.');
    }

    public function classement(): View
    {
        abort_unless(auth()->user()->can('ged.voir'), 403);

        return view('ged.classement.index', [
            'categories' => GedCategorie::with(['enfants', 'typesDocuments'])->whereNull('parent_id')->get(),
            'dossiers' => GedDossier::with('sousDossiers')->whereNull('parent_id')->get(),
        ]);
    }

    public function versions(): View
    {
        abort_unless(auth()->user()->can('ged.voir'), 403);

        return view('ged.versions.index', [
            'versions' => GedVersion::with(['document', 'auteur'])->latest()->paginate(20),
        ]);
    }

    public function signatures(): View
    {
        abort_unless(auth()->user()->can('ged.voir'), 403);

        return view('ged.signatures.index', [
            'signatures' => GedSignature::with(['document', 'signataire'])->latest()->paginate(20),
        ]);
    }

    public function demanderSignature(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('ged.signer') || auth()->user()->can('ged.admin'), 403);

        $request->validate([
            'document_id' => ['required', 'exists:ged_documents,id'],
            'signataire_id' => ['required', 'exists:users,id'],
        ]);

        $document = \App\Models\Ged\GedDocument::findOrFail($request->input('document_id'));
        $signataire = User::findOrFail($request->input('signataire_id'));

        $this->signatures->demander($document, $request->user(), $signataire);

        return back()->with('succes', 'Demande de signature envoyée.');
    }

    public function signer(GedSignature $signature): RedirectResponse
    {
        $this->signatures->signer($signature, auth()->user());

        return back()->with('succes', 'Document signé.');
    }

    public function rejeterSignature(Request $request, GedSignature $signature): RedirectResponse
    {
        $request->validate(['motif' => ['required', 'string']]);
        $this->signatures->rejeter($signature, auth()->user(), $request->input('motif'));

        return back()->with('succes', 'Signature rejetée.');
    }

    public function verifier(string $uuid): View
    {
        $document = \App\Models\Ged\GedDocument::where('uuid', $uuid)->firstOrFail();

        return view('ged.signatures.verifier', [
            'document' => $document,
            'resultat' => $this->signatures->verifier($document),
        ]);
    }

    public function workflows(): View
    {
        abort_unless(auth()->user()->can('ged.admin'), 403);

        return view('ged.workflows.index', [
            'workflows' => GedWorkflow::with('etapes')->paginate(20),
        ]);
    }

    public function validations(): View
    {
        abort_unless(auth()->user()->can('ged.valider'), 403);

        return view('ged.validations.index', [
            'validations' => GedValidation::with(['document', 'validateur', 'etape'])
                ->where('statut', 'en_attente')->paginate(20),
        ]);
    }

    public function valider(GedValidation $validation): RedirectResponse
    {
        $this->workflow->valider($validation, auth()->user(), request('commentaire'));

        return back()->with('succes', 'Validation enregistrée.');
    }

    public function rejeterValidation(Request $request, GedValidation $validation): RedirectResponse
    {
        $request->validate(['motif' => ['required', 'string']]);
        $this->workflow->rejeter($validation, auth()->user(), $request->input('motif'));

        return back()->with('succes', 'Document rejeté.');
    }

    public function conservation(): View
    {
        abort_unless(auth()->user()->can('ged.admin'), 403);

        return view('ged.conservation.index', [
            'regles' => GedConservationRule::with(['typeDocument', 'categorie'])->paginate(20),
        ]);
    }

    public function journal(): View
    {
        abort_unless(auth()->user()->can('ged.consulter_journal'), 403);

        return view('ged.journal.index', [
            'journaux' => GedJournal::with(['document', 'utilisateur'])->latest()->paginate(30),
        ]);
    }

    public function parametres(): View
    {
        abort_unless(auth()->user()->can('ged.admin'), 403);

        return view('ged.parametres.index', [
            'antivirus' => app(\App\Services\Ged\GedAntivirusService::class)->statut(),
            'signature' => $this->signatures->statut(),
        ]);
    }
}
