<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Structure;
use App\Models\User;
use App\Services\Pap\PapAuditService;
use App\Services\Pap\PapEnveloppeService;
use App\Services\Pap\PapNotificationService;
use App\Services\Pap\PapPerformanceService;
use App\Services\Pap\PapPilotageService;
use App\Services\Pap\PapReferentielService;
use App\Services\WorkflowService;
use App\Http\Requests\Pap\PapRequest;
use App\Http\Requests\Pap\PapRevisionRequest;
use App\Http\Requests\WorkflowDecisionRequest;
use App\Support\WorkflowRejetCategorie;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PapController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly PapAuditService $audit,
        private readonly PapPerformanceService $performance,
        private readonly PapPilotageService $pilotage,
        private readonly PapReferentielService $referentiel,
        private readonly PapEnveloppeService $enveloppe,
        private readonly PapNotificationService $notifications,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Pap::class);

        $paps = Pap::with('exercice', 'workflowActif', 'responsable', 'structure')->latest('exercice_id')->paginate(20);

        return view('paps.index', compact('paps'));
    }

    public function create(): View
    {
        $this->authorize('create', Pap::class);

        $exercicesDisponibles = Exercice::query()
            ->whereDoesntHave('pap')
            ->orderByDesc('annee')
            ->get();

        return view('paps.create', [
            'exercices' => $exercicesDisponibles,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'type']),
            'responsables' => User::query()->where('actif', true)->orderBy('name')->get(['id', 'name']),
            'referenceSuggestion' => $exercicesDisponibles->first()
                ? $this->enveloppe->genererReference($exercicesDisponibles->first())
                : null,
        ]);
    }

    public function store(PapRequest $request): RedirectResponse
    {
        $this->authorize('create', Pap::class);

        $data = $request->validated();

        try {
            $pap = $this->enveloppe->creer($data, (int) auth()->id());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('paps.show', $pap)->with('succes', 'PAP créé avec succès.');
    }

    public function show(Pap $pap): View
    {
        $this->authorize('view', $pap);

        $pap->load([
            'exercice', 'createur', 'responsable', 'structure', 'validateur',
            'workflowActif.workflow', 'workflowActif.etapes.acteur',
            'historiques.auteur',
        ]);
        $instance = $pap->workflowActif;
        $peutAgir = auth()->user()->can('agir', $pap);

        $synthese = $this->performance->executionPap($pap->exercice_id);
        $audit = $this->audit->executer($pap->exercice_id);
        $consolidation = $this->performance->consolidationParStructure($pap->exercice_id)->take(8);
        $classementDepartements = $this->performance->classementDepartements($pap->exercice_id)->take(8);
        $performanceProduits = $this->performance->performanceProduits($pap->exercice_id);
        $performanceActivites = $this->performance->performanceActivites($pap->exercice_id);
        $alertes = $this->pilotage->alertes($pap->exercice_id);
        $risques = $pap->risques()->with(['activite', 'responsable'])->orderBy('code')->get();
        $mesures = $pap->mesuresCorrectives()->with(['risque', 'activite', 'responsable'])->latest()->get();
        $activitesEcarts = Activite::query()
            ->with('structure')
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $pap->exercice_id))
            ->orderBy('code')
            ->get(['id', 'code', 'intitule', 'cause_ecart', 'mesure_corrective', 'taux_realisation_physique', 'taux_realisation_financier']);
        $activitesSelect = $activitesEcarts->map(fn ($a) => ['id' => $a->id, 'label' => $a->code.' — '.Str::limit($a->intitule, 40)]);
        $responsables = User::where('actif', true)->orderBy('name')->get(['id', 'name']);

        return view('paps.show', compact(
            'pap', 'instance', 'peutAgir', 'synthese', 'audit', 'consolidation', 'classementDepartements',
            'performanceProduits', 'performanceActivites', 'alertes',
            'risques', 'mesures', 'activitesEcarts', 'activitesSelect', 'responsables',
        ));
    }

    public function pdf(Pap $pap): Response
    {
        $this->authorize('view', $pap);

        $pap->load(['exercice', 'createur', 'responsable', 'structure', 'validateur']);

        $synthese = $this->performance->executionPap($pap->exercice_id);
        $consolidation = $this->performance->consolidationParStructure($pap->exercice_id)->take(12);
        $classementDepartements = $this->performance->classementDepartements($pap->exercice_id)->take(12);
        $performanceProduits = $this->performance->performanceProduits($pap->exercice_id);
        $performanceActivites = $this->performance->performanceActivites($pap->exercice_id);
        $risques = $pap->risques()->with(['activite', 'responsable'])->orderBy('code')->get();

        $pdf = Pdf::loadView('paps.pdf.fiche', compact(
            'pap', 'synthese', 'consolidation', 'classementDepartements',
            'performanceProduits', 'performanceActivites', 'risques',
        ))->setPaper('a4', 'portrait');

        $nom = 'pap-'.$pap->exercice?->annee.'-'.now()->format('Ymd').'.pdf';

        return $pdf->stream($nom);
    }

    public function edit(Pap $pap): View
    {
        $this->authorize('update', $pap);

        return view('paps.edit', [
            'pap' => $pap->load('exercice'),
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'type']),
            'responsables' => User::query()->where('actif', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(PapRequest $request, Pap $pap): RedirectResponse
    {
        $this->authorize('update', $pap);

        $data = $request->validated();

        try {
            $this->enveloppe->mettreAJour($pap, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('paps.show', $pap)->with('succes', 'PAP mis à jour.');
    }

    public function destroy(Pap $pap): RedirectResponse
    {
        $this->authorize('delete', $pap);

        try {
            $this->enveloppe->supprimer($pap);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('paps.index')->with('succes', 'PAP supprimé.');
    }

    public function soumettre(Pap $pap): RedirectResponse
    {
        $this->authorize('soumettre', $pap);

        if (! $this->audit->peutSoumettre($pap->exercice_id)) {
            $rapport = $this->audit->executer($pap->exercice_id);

            return back()->with('erreur', 'Soumission impossible : '.$rapport['anomalies_critiques'].' anomalie(s) critique(s). Exécutez pap:audit pour le détail.');
        }

        if (in_array($pap->statut, ['rejete', 'en_revision'], true)) {
            $pap->increment('version');
        }

        $action = $pap->statut === 'en_revision' ? 'resoumission_revision' : 'soumission';
        $this->referentiel->historiser($pap, $action, auth()->id());

        try {
            $instance = $this->workflow->demarrer($pap, 'PAP', auth()->user());
            $this->notifications->notifierSoumission($pap->fresh(), $instance);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'PAP soumis au circuit de validation.');
    }

    public function agir(WorkflowDecisionRequest $request, Pap $pap): RedirectResponse
    {
        $this->authorize('agir', $pap);

        $data = $request->validated();

        $instance = $pap->workflowActif;
        if (! $instance) {
            return back()->with('erreur', 'Aucun circuit actif sur ce PAP.');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                auth()->user(),
                $data['decision'],
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? WorkflowRejetCategorie::DOCUMENTAIRE,
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        if ($data['decision'] === 'rejeter') {
            $pap->update([
                'motif_rejet' => $data['observation'],
                'categorie_rejet' => $data['categorie_rejet'] ?? WorkflowRejetCategorie::DOCUMENTAIRE,
            ]);
        }

        $this->notifications->notifierDecision($pap->fresh(), $instance->fresh(), $data['decision'], auth()->user());

        return back()->with('succes', 'Décision enregistrée.');
    }

    public function demanderRevision(PapRevisionRequest $request, Pap $pap): RedirectResponse
    {
        $this->authorize('reviser', $pap);

        $data = $request->validated();

        try {
            $this->referentiel->demanderRevision($pap, auth()->id(), $data['motif']);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'PAP ouvert en révision — le référentiel RBM est modifiable.');
    }
}
