<?php

namespace App\Http\Controllers;

use App\Authorization\StructureBudget;
use App\Http\Requests\StructureBudgetLigneRequest;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\PartenaireFinancier;
use App\Models\Pilier;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Models\Strategie\ProjetStrategique;
use App\Policies\StructureBudgetPolicy;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\RbmBudgetSyncService;
use App\Services\Budget\StructureBudgetCoherenceService;
use App\Services\Budget\StructureBudgetJournalService;
use App\Services\Budget\StructureBudgetService;
use App\Services\Budget\StructureBudgetValidationService;
use App\Services\Budget\StructureBudgetWizardService;
use App\Services\Reporting\ReportingService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Module « Structure du Budget » — restitution unifiée, recherche, CRUD in-module des lignes.
 */
class StructureBudgetController extends Controller
{
    public function __construct(
        private StructureBudgetService $service,
        private StructureBudgetWizardService $wizard,
        private StructureBudgetCoherenceService $coherence,
        private StructureBudgetValidationService $validation,
        private StructureBudgetJournalService $journal,
        private RbmBudgetSyncService $rbmBudgetSync,
        private ExerciceService $exerciceService,
    ) {
    }

    /** S1 — Arbre institutionnel budgété. */
    public function arbre(Request $request): View
    {
        $this->authorize('viewAny', StructureBudget::class);
        [$exercices, $exercice] = $this->exercices($request);

        return view('structure-budget.arbre', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'arbre' => $this->service->arbre($exercice?->id ?? 0),
            'synthese' => $this->service->synthese($exercice?->id ?? 0),
        ]);
    }

    /** S2 — Arbre complet : institutionnel + chaîne programmatique PAP. */
    public function arbreComplet(Request $request): View
    {
        $this->authorize('viewAny', StructureBudget::class);
        [$exercices, $exercice] = $this->exercices($request);
        $exerciceId = $exercice?->id ?? 0;

        return view('structure-budget.arbre-complet', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'donnees' => $this->service->arbreComplet($exerciceId),
        ]);
    }

    /** Recherche multicritère des lignes budgétaires. */
    public function recherche(Request $request): View
    {
        $this->authorize('viewAny', StructureBudget::class);
        [$exercices, $exercice] = $this->exercices($request);
        $exerciceId = $exercice?->id ?? 0;
        $filtres = $request->only(['q', 'structure', 'pilier', 'rubrique', 'source', 'projet', 'montant_min', 'montant_max']);

        return view('structure-budget.recherche', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'filtres' => $filtres,
            'lignes' => $exerciceId ? $this->service->rechercher($exerciceId, $filtres) : collect(),
            'structures' => Structure::actif()->orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'piliers' => $exerciceId ? Pilier::where('exercice_id', $exerciceId)->orderBy('code')->get(['id', 'code', 'intitule']) : collect(),
            'rubriques' => BudgetRubrique::orderBy('code')->get(['id', 'code', 'intitule']),
            'sources' => SourceFinancement::where('actif', true)->orderBy('code')->get(['id', 'code', 'libelle']),
            'projets' => ProjetStrategique::orderBy('code')->limit(200)->get(['id', 'code', 'intitule']),
        ]);
    }

    /** Détail d'un nœud institutionnel : lignes rattachées. */
    public function structure(Request $request, Structure $structure): View
    {
        $this->authorize('viewAny', StructureBudget::class);
        [$exercices, $exercice] = $this->exercices($request);

        return view('structure-budget.structure', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'structure' => $structure,
            'lignes' => $this->service->lignes($exercice?->id ?? 0, $structure->id),
        ]);
    }

    /** Fiche détaillée d'une ligne (PAP, nomenclature, imputations). */
    public function showLigne(Request $request, LigneBudgetaire $ligne): View
    {
        $this->authorize('structureBudget.viewLigne', $ligne);
        [$exercices, $exercice] = $this->exercices($request);
        $ctx = $this->service->contexteLigne($ligne);

        return view('structure-budget.ligne.show', array_merge($ctx, [
            'exercices' => $exercices,
            'exercice' => $exercice ?? $ligne->exercice,
        ]));
    }

    /** S2 — Tableau de bord consolidé (Lot 5 : anomalies, évolution N/N-1). */
    public function tableauBord(Request $request, ReportingService $reporting): View
    {
        $this->authorize('viewAny', StructureBudget::class);
        [$exercices, $exercice] = $this->exercices($request);
        $exerciceId = $exercice?->id ?? 0;

        return view('structure-budget.tableau', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'synthese' => $this->service->synthese($exerciceId),
            'parDepartement' => $this->service->totauxParType($exerciceId, 'departement'),
            'parDirection' => $this->service->totauxParType($exerciceId, 'direction'),
            'parService' => $this->service->totauxParType($exerciceId, 'service'),
            'parPilier' => $exerciceId ? $reporting->parPilier($exerciceId) : collect(),
            'parBailleur' => $exerciceId ? $reporting->parBailleur($exerciceId)['sources'] : collect(),
            'parPartenaire' => $exerciceId ? $reporting->parPartenaire($exerciceId) : collect(),
            'anomalies' => $exerciceId ? $this->coherence->anomalies($exerciceId) : [],
            'evolution' => $exerciceId ? $this->coherence->evolution($exerciceId) : null,
            'repartitionSens' => $exerciceId ? $this->coherence->repartitionParSens($exerciceId) : collect(),
            'ecartsPap' => $exerciceId ? $this->coherence->ecartsMontantPap($exerciceId)->take(10) : collect(),
            'peutReconcilier' => $request->user()->can('structure_budget.modifier'),
        ]);
    }

    /** Lot 5 — Réconciliation automatique des liaisons activité ↔ ligne. */
    public function reconcilier(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', StructureBudget::class);
        abort_unless($request->user()->can('structure_budget.modifier'), 403);

        [, $exercice] = $this->exercices($request);
        if (! $exercice) {
            return back()->with('erreur', 'Sélectionnez un exercice.');
        }

        try {
            $this->exerciceService->assertBudgetModifiable($exercice->id);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $stats = $this->coherence->reconcilier($exercice->id, $this->rbmBudgetSync);

        return redirect()
            ->route('structure-budget.tableau', ['exercice' => $exercice->id])
            ->with('succes', "Réconciliation effectuée : {$stats['activites']} activité(s), {$stats['lignes']} ligne(s) synchronisée(s).");
    }

    /** S3 — Assistant simple (redirige vers le wizard). */
    public function assistant(Request $request): RedirectResponse
    {
        return redirect()->route('structure-budget.wizard', $request->only(['exercice', 'structure']));
    }

    /** Lot 3 — Wizard de saisie multi-étapes. */
    public function wizard(Request $request): View
    {
        $this->authorize('create', StructureBudget::class);
        [$exercices, $exercice] = $this->exercices($request);

        return view('structure-budget.wizard', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'structures' => Structure::actif()->orderBy('code')->get(['id', 'code', 'libelle', 'sigle', 'type']),
            'rubriques' => BudgetRubrique::orderBy('code')->get(['id', 'code', 'intitule', 'statut']),
            'sources' => SourceFinancement::where('actif', true)->orderBy('code')->get(['id', 'code', 'libelle', 'partenaire_id']),
            'partenaires' => PartenaireFinancier::where('actif', true)->orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'codeSuggere' => $exercice ? $this->wizard->codeSuggere($exercice) : '',
        ]);
    }

    public function wizardContexte(Request $request): JsonResponse
    {
        $this->authorize('create', StructureBudget::class);

        return response()->json($this->wizard->contexte(
            $request->integer('exercice_id'),
            $request->filled('structure_id') ? $request->integer('structure_id') : null,
        ));
    }

    public function wizardActivites(Request $request): JsonResponse
    {
        $this->authorize('create', StructureBudget::class);

        return response()->json([
            'activites' => $this->wizard->activites(
                $request->integer('exercice_id'),
                $request->integer('structure_id'),
            ),
        ]);
    }

    public function wizardRubrique(BudgetRubrique $rubrique): JsonResponse
    {
        $this->authorize('create', StructureBudget::class);
        $chaine = $this->wizard->chaineRubrique($rubrique->id);

        return response()->json($chaine ?? ['chemin' => [], 'statut_ok' => false]);
    }

    public function wizardVerifier(Request $request): JsonResponse
    {
        $this->authorize('create', StructureBudget::class);

        return response()->json($this->wizard->verifier(
            $request->integer('exercice_id'),
            $request->only(['structure_id', 'activite_id', 'rubrique_id', 'code', 'montant_ouvert']),
        ));
    }

    public function wizardStore(StructureBudgetLigneRequest $request): RedirectResponse
    {
        return $this->creerLigne($request);
    }

    /** AJAX — activités filtrées par structure (legacy assistant). */
    public function activitesDeStructure(Request $request): JsonResponse
    {
        $this->authorize('create', StructureBudget::class);
        $exerciceId = $request->integer('exercice_id') ?: ExerciceService::courant()?->id ?? 0;

        return response()->json([
            'activites' => $this->wizard->activites($exerciceId, $request->integer('structure_id')),
        ]);
    }

    /** Création d'une ligne via l'assistant. */
    public function creerLigne(StructureBudgetLigneRequest $request): RedirectResponse
    {
        $this->authorize('create', StructureBudget::class);

        try {
            $this->exerciceService->assertBudgetModifiable((int) $request->exercice_id);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $ligne = LigneBudgetaire::create($request->validated() + ['actif' => true]);
        $this->rbmBudgetSync->synchroniserLigne($ligne);
        AuditLogger::enregistrer('structure_budget_ligne_creee', $ligne, null, [
            'structure_id' => $ligne->structure_id, 'code' => $ligne->code,
        ]);

        return redirect()
            ->route('structure-budget.ligne.show', ['ligne' => $ligne, 'exercice' => $ligne->exercice_id])
            ->with('succes', "Ligne {$ligne->code} créée.");
    }

    /** Édition in-module d'une ligne. */
    public function editLigne(LigneBudgetaire $ligne): View
    {
        $this->authorize('structureBudget.updateLigne', $ligne);

        return view('structure-budget.ligne.edit', [
            'ligne' => $ligne,
            'exercice' => $ligne->exercice,
            'structures' => Structure::actif()->orderBy('code')->get(['id', 'code', 'libelle', 'sigle', 'type']),
            'rubriques' => BudgetRubrique::orderBy('code')->get(['id', 'code', 'intitule']),
            'sources' => SourceFinancement::where('actif', true)->orderBy('code')->get(['id', 'code', 'libelle']),
        ]);
    }

    /** Mise à jour in-module. */
    public function updateLigne(StructureBudgetLigneRequest $request, LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('structureBudget.updateLigne', $ligne);

        try {
            $this->exerciceService->assertBudgetModifiable($ligne->exercice_id);
            $this->validation->assertModifiable($ligne);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $avant = $ligne->only(['code', 'montant_ouvert', 'structure_id', 'activite_id', 'statut_validation']);
        $ligne->update($request->validated());
        $this->rbmBudgetSync->synchroniserLigne($ligne->fresh());
        AuditLogger::enregistrer('structure_budget_ligne_modifiee', $ligne, $avant, $ligne->only(['code', 'montant_ouvert', 'structure_id', 'activite_id']));

        return redirect()
            ->route('structure-budget.ligne.show', ['ligne' => $ligne, 'exercice' => $ligne->exercice_id])
            ->with('succes', 'Ligne mise à jour.');
    }

    /** Suppression physique si aucun mouvement d'exécution. */
    public function destroyLigne(LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('structureBudget.deleteLigne', $ligne);

        if ($raison = $ligne->raisonSuppressionInterdite()) {
            return back()->with('erreur', $raison);
        }

        try {
            $this->exerciceService->assertBudgetModifiable($ligne->exercice_id);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $exId = $ligne->exercice_id;
        $code = $ligne->code;
        AuditLogger::enregistrer('structure_budget_ligne_supprimee', $ligne, $ligne->toArray(), null);
        $ligne->delete();

        return redirect()
            ->route('structure-budget.recherche', ['exercice' => $exId])
            ->with('succes', "Ligne {$code} supprimée.");
    }

    /** Désactivation logique (archivage) — conserve l'historique. */
    public function archiveLigne(LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('structureBudget.archiveLigne', $ligne);

        $ligne->update(['actif' => false]);
        AuditLogger::enregistrer('structure_budget_ligne_archivee', $ligne, ['actif' => true], ['actif' => false]);

        return back()->with('succes', "Ligne {$ligne->code} désactivée.");
    }

    /** Lot 6 — Soumettre une ligne au circuit de validation. */
    public function soumettreLigne(Request $request, LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('structureBudget.submitLigne', $ligne);

        try {
            $this->exerciceService->assertBudgetModifiable($ligne->exercice_id);
            $this->validation->soumettre($ligne, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "Ligne {$ligne->code} soumise à validation.");
    }

    /** Lot 6 — Valider une ligne soumise. */
    public function validerLigne(Request $request, LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('structureBudget.validateLigne', $ligne);

        try {
            $this->validation->valider($ligne, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "Ligne {$ligne->code} validée.");
    }

    /** Lot 6 — Rejeter une ligne soumise. */
    public function rejeterLigne(Request $request, LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('structureBudget.rejectLigne', $ligne);

        $request->validate(['motif_rejet' => ['required', 'string', 'max:1000']]);

        try {
            $this->validation->rejeter($ligne, $request->user(), $request->input('motif_rejet'));
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "Ligne {$ligne->code} rejetée — retour en correction.");
    }

    /** Lot 6 — Journal des opérations du module. */
    public function journal(Request $request): View
    {
        abort_unless(app(StructureBudgetPolicy::class)->viewJournal($request->user()), 403);

        [$exercices, $exercice] = $this->exercices($request);

        return view('structure-budget.journal.index', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'entrees' => $exercice
                ? $this->journal->journalExercice($exercice->id, $request->input('action'))
                : collect(),
            'actions' => $this->journal->actionsDisponibles(),
            'enAttente' => $exercice ? $this->journal->lignesEnAttente($exercice->id) : collect(),
        ]);
    }

    // ---- Internes ---------------------------------------------------------------------------

    private function exercices(Request $request): array
    {
        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? $exercices->firstWhere('statut', 'ouvert')
            ?? $exercices->first();

        return [$exercices, $exercice];
    }

    private function codeSuggere(Exercice $exercice): string
    {
        $n = LigneBudgetaire::where('exercice_id', $exercice->id)->count() + 1;

        return sprintf('LB-%d-%04d', $exercice->annee, $n);
    }
}
