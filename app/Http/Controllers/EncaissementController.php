<?php

namespace App\Http\Controllers;

use App\Http\Requests\EncaissementRequest;
use App\Models\Encaissement;
use App\Models\Recette;
use App\Services\Ged\GedAttachmentService;
use App\Services\Ged\GedDocumentService;
use App\Services\Recettes\EncaissementService;
use App\Services\WorkflowService;
use App\Http\Requests\WorkflowDecisionSansRenvoiRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EncaissementController extends Controller
{
    public function __construct(
        private readonly EncaissementService $service,
        private readonly GedAttachmentService $ged,
        private readonly GedDocumentService $gedDocuments,
        private readonly WorkflowService $workflow,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Encaissement::class);

        $encaissements = Encaissement::query()
            ->with(['recette.categorie', 'recette.etatMembre', 'saisiPar', 'validePar'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('recette'), fn ($q) => $q->where('recette_id', $request->recette))
            ->orderByDesc('date_encaissement')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $kpis = [
            'comptabilise' => (float) Encaissement::whereIn('statut', ['valide', 'rapproche'])->sum('montant_xaf'),
            'en_attente' => Encaissement::where('statut', 'saisi')->count(),
            'rapproches' => Encaissement::where('statut', 'rapproche')->count(),
        ];

        return view('encaissements.index', compact('encaissements', 'kpis'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Encaissement::class);

        $recettes = Recette::query()
            ->with(['categorie', 'etatMembre'])
            ->whereIn('statut', ['prevue', 'partiellement_realisee'])
            ->orderBy('reference')
            ->get();

        return view('encaissements.create', [
            'encaissement' => new Encaissement(['devise' => 'XAF', 'taux_change' => 1, 'mode_paiement' => 'virement']),
            'recettes' => $recettes,
            'recetteSelection' => $request->integer('recette') ?: null,
        ]);
    }

    public function store(EncaissementRequest $request): RedirectResponse
    {
        $this->authorize('create', Encaissement::class);

        try {
            $encaissement = $this->service->saisir($request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $nb = $this->attacherJustificatifs($encaissement, $request);

        $message = $nb > 0
            ? "Encaissement saisi avec {$nb} justificatif(s). Vous pouvez le valider."
            : 'Encaissement saisi. Rattachez au moins un justificatif sur la fiche, puis validez.';

        return redirect()->route('encaissements.show', $encaissement)->with('succes', $message);
    }

    /**
     * Rattache les justificatifs téléversés au module GED (documents probants,
     * versionnés et archivables), liés polymorphiquement à l'encaissement.
     */
    private function attacherJustificatifs(Encaissement $encaissement, EncaissementRequest $request): int
    {
        $fichiers = array_filter((array) $request->file('justificatifs', []));
        foreach ($fichiers as $fichier) {
            $this->ged->rattacherDepuisModule(
                'encaissement',
                $encaissement->id,
                $fichier,
                ['titre' => 'Justificatif encaissement '.$encaissement->reference.' — '.$fichier->getClientOriginalName()],
                $request->user(),
                $this->gedDocuments,
            );
        }

        return count($fichiers);
    }

    /** Ajout d'un justificatif GED après la saisie (depuis la fiche). */
    public function ajouterJustificatif(Request $request, Encaissement $encaissement): RedirectResponse
    {
        $this->authorize('create', Encaissement::class);

        $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,csv'],
            'titre' => ['nullable', 'string', 'max:255'],
        ]);

        $fichier = $request->file('fichier');
        $this->ged->rattacherDepuisModule(
            'encaissement',
            $encaissement->id,
            $fichier,
            ['titre' => $request->filled('titre') ? $request->input('titre') : $fichier->getClientOriginalName()],
            $request->user(),
            $this->gedDocuments,
        );

        return back()->with('succes', 'Justificatif rattaché à la GED.');
    }

    public function show(Encaissement $encaissement): View
    {
        $this->authorize('view', $encaissement);

        $encaissement->load([
            'recette.exercice', 'recette.categorie', 'recette.etatMembre',
            'saisiPar', 'validePar', 'rapprochePar', 'pieces',
            'workflowActif.workflow', 'workflowActif.etapes.acteur',
        ]);

        return view('encaissements.show', [
            'encaissement' => $encaissement,
            'instance' => $encaissement->workflowActif,
            // Évalué sur le circuit lui-même : le contournement administrateur de la Gate afficherait
            // le panneau de décision sans instance en cours.
            'peutAgir' => $encaissement->workflowActif !== null
                && $this->workflow->peutAgir($encaissement->workflowActif, auth()->user()),
            'etC' => ['en_attente' => 'secondary', 'valide' => 'success', 'rejete' => 'danger', 'renvoye' => 'warning'],
            'blocages' => in_array($encaissement->statut, ['saisi', 'rejete'], true)
                ? $this->service->blocagesSoumission($encaissement)
                : [],
        ]);
    }

    public function soumettre(Encaissement $encaissement): RedirectResponse
    {
        $this->authorize('soumettre', $encaissement);

        try {
            $this->service->assertPretPourValidation($encaissement);
            $this->workflow->demarrer($encaissement, 'RECETTES', auth()->user());
            app(\App\Services\Notifications\RecettesNotificationService::class)
                ->notifierEncaissementSoumis($encaissement->fresh());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Encaissement soumis au circuit de validation recettes.');
    }

    public function agir(WorkflowDecisionSansRenvoiRequest $request, Encaissement $encaissement): RedirectResponse
    {
        $this->authorize('agir', $encaissement);
        $data = $request->validated();

        $instance = $encaissement->workflowActif;
        if (! $instance) {
            return back()->with('erreur', 'Cet encaissement n\'est pas en cours de validation : soumettez-le d\'abord au circuit (bouton « Soumettre »).');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                $request->user(),
                $data['decision'],
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Décision enregistrée.');
    }

    /** @deprecated Utiliser le circuit workflow RECETTES (soumettre / agir). */
    public function valider(Encaissement $encaissement): RedirectResponse
    {
        return $this->soumettre($encaissement);
    }

    public function rapprocher(Request $request, Encaissement $encaissement): RedirectResponse
    {
        $this->authorize('rapprocher', $encaissement);

        $data = $request->validate([
            'date_rapprochement' => ['required', 'date'],
            'reference_bancaire' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->service->rapprocher($encaissement, $data, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Encaissement rapproché.');
    }

    public function annuler(Encaissement $encaissement): RedirectResponse
    {
        $this->authorize('annuler', $encaissement);

        try {
            $this->service->annuler($encaissement);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Encaissement annulé.');
    }

    public function destroy(Encaissement $encaissement): RedirectResponse
    {
        $this->authorize('delete', $encaissement);

        try {
            $encaissement->delete();
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('encaissements.index')->with('succes', 'Encaissement supprimé.');
    }
}
