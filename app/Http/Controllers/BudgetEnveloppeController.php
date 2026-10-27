<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\PrevisionRecette;
use App\Models\Structure;
use App\Models\User;
use App\Services\Budget\BudgetAuditService;
use App\Services\Budget\BudgetEnveloppeService;
use App\Services\Budget\BudgetNotificationService;
use App\Services\Budget\BudgetReferentielService;
use App\Services\WorkflowService;
use App\Http\Requests\WorkflowDecisionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Enveloppe budgétaire institutionnelle — cycle adoption (phase B1). */
class BudgetEnveloppeController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly BudgetAuditService $audit,
        private readonly BudgetReferentielService $referentiel,
        private readonly BudgetEnveloppeService $enveloppe,
        private readonly BudgetNotificationService $notifications,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Budget::class);

        $budgets = Budget::with('exercice', 'workflowActif', 'responsable', 'structure')
            ->latest('exercice_id')
            ->paginate(20);

        return view('budget.enveloppe.index', compact('budgets'));
    }

    public function create(): View
    {
        $this->authorize('create', Budget::class);

        $exercicesDisponibles = Exercice::query()
            ->whereDoesntHave('budgetInstitutionnel')
            ->orderByDesc('annee')
            ->get();

        $syntheses = $exercicesDisponibles->mapWithKeys(function (Exercice $exercice) {
            $depenses = (float) LigneBudgetaire::query()
                ->where('exercice_id', $exercice->id)
                ->sum(DB::raw('COALESCE(montant_revise, montant_ouvert)'));

            $recettes = (float) PrevisionRecette::query()
                ->where('exercice_id', $exercice->id)
                ->courante()
                ->valide()
                ->sum(DB::raw('COALESCE(montant_revise, montant_prevu)'));

            return [$exercice->id => [
                'annee' => $exercice->annee,
                'devise' => $exercice->devise ?: 'XAF',
                'statut' => $exercice->statut,
                'reference' => $this->enveloppe->genererReference($exercice),
                'lignes' => LigneBudgetaire::query()->where('exercice_id', $exercice->id)->count(),
                'depenses' => $depenses,
                'recettes' => $recettes,
                'solde' => $recettes - $depenses,
            ]];
        });

        return view('budget.enveloppe.create', [
            'exercices' => $exercicesDisponibles,
            'syntheses' => $syntheses,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'type']),
            'responsables' => User::query()->where('actif', true)->orderBy('name')->get(['id', 'name']),
            'referenceSuggestion' => $exercicesDisponibles->first()
                ? $this->enveloppe->genererReference($exercicesDisponibles->first())
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Budget::class);

        $data = $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id', 'unique:budgets,exercice_id'],
            'reference' => ['nullable', 'string', 'max:40', 'unique:budgets,reference'],
            'libelle' => ['required', 'string', 'max:255'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'date_elaboration' => ['nullable', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $budget = $this->enveloppe->creer($data, (int) auth()->id());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('budget.enveloppe.show', $budget)->with('succes', 'Enveloppe budgétaire créée.');
    }

    public function show(Budget $budget): View
    {
        $this->authorize('view', $budget);

        $budget->load([
            'exercice', 'createur', 'responsable', 'structure', 'validateur',
            'workflowActif.workflow', 'workflowActif.etapes.acteur',
            'historiques.auteur',
        ]);

        $instance = $budget->workflowActif;
        $peutAgir = $instance && $this->workflow->peutAgir($instance, auth()->user());
        $audit = $this->audit->executer($budget->exercice_id);
        $montantTotal = $this->enveloppe->calculerMontantTotal($budget->exercice_id);

        return view('budget.enveloppe.show', compact('budget', 'instance', 'peutAgir', 'audit', 'montantTotal'));
    }

    public function edit(Budget $budget): View
    {
        $this->authorize('update', $budget);

        return view('budget.enveloppe.edit', [
            'budget' => $budget,
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'type']),
            'responsables' => User::query()->where('actif', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'date_elaboration' => ['nullable', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->enveloppe->mettreAJour($budget, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('budget.enveloppe.show', $budget)->with('succes', 'Budget mis à jour.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        try {
            $this->enveloppe->supprimer($budget);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('budget.enveloppe.index')->with('succes', 'Budget supprimé.');
    }

    public function soumettre(Budget $budget): RedirectResponse
    {
        $this->authorize('soumettre', $budget);

        if (! $this->audit->peutSoumettre($budget->exercice_id)) {
            $rapport = $this->audit->executer($budget->exercice_id);

            return back()->with('erreur', 'Soumission impossible : '.$rapport['anomalies_total'].' anomalie(s). Exécutez budget:audit pour le détail.');
        }

        if (in_array($budget->statut, ['rejete', 'en_revision'], true)) {
            $budget->increment('version');
        }

        $action = $budget->statut === 'en_revision' ? 'resoumission_revision' : 'soumission';
        $this->referentiel->historiser($budget, $action, auth()->id());
        $budget->update(['montant_total' => $this->enveloppe->calculerMontantTotal($budget->exercice_id)]);

        try {
            $instance = $this->workflow->demarrer($budget, 'BUDGET', auth()->user());
            $this->notifications->notifierSoumission($budget->fresh(), $instance);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Budget soumis au circuit de validation.');
    }

    public function agir(WorkflowDecisionRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorize('agir', $budget);

        $data = $request->validated();

        $instance = $budget->workflowActif;
        if (! $instance) {
            return back()->with('erreur', 'Aucun circuit actif sur ce budget.');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                auth()->user(),
                $data['decision'],
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        if ($data['decision'] === 'rejeter') {
            $budget->update([
                'motif_rejet' => $data['observation'],
                'categorie_rejet' => $data['categorie_rejet'],
            ]);
        }

        $this->notifications->notifierDecision($budget->fresh(), $instance->fresh(), $data['decision'], auth()->user());

        return back()->with('succes', 'Décision enregistrée.');
    }

    public function demanderRevision(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('reviser', $budget);

        $data = $request->validate([
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->referentiel->demanderRevision($budget, auth()->id(), $data['motif']);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Budget ouvert en révision — les lignes budgétaires sont modifiables.');
    }
}
