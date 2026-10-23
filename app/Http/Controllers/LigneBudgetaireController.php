<?php

namespace App\Http\Controllers;

use App\Http\Requests\LigneBudgetaireRequest;
use App\Models\Budget;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Services\AccessScopeService;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\LigneFinancementService;
use App\Services\Budget\RbmBudgetSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class LigneBudgetaireController extends Controller
{
    public function __construct(
        private readonly AccessScopeService $scopes,
        private readonly RbmBudgetSyncService $rbmBudgetSync,
        private readonly ExerciceService $exerciceService,
        private readonly LigneFinancementService $financementService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LigneBudgetaire::class);

        $exercice = $this->exercice($request);

        $base = LigneBudgetaire::query()->where('exercice_id', $exercice?->id);
        $base = $this->scopes->filtrerStructures($base, $request->user());
        $base = $this->appliquerFiltresListe($base, $request);

        $lignes = (clone $base)
            ->with('rubrique', 'structure', 'sourceFinancement')
            ->orderBy('code')->paginate(25)->withQueryString();

        $totaux = (clone $base)->selectRaw(
            'SUM(montant_ouvert) o, SUM(montant_engage) e, SUM(montant_liquide) l, SUM(montant_ordonnance) ord, SUM(montant_paye) p'
        )->first();

        return view('budget.index', [
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'enveloppe' => $exercice ? Budget::where('exercice_id', $exercice->id)->first() : null,
            'lignesVerrouillees' => $exercice ? $this->exerciceService->budgetLignesVerrouillees($exercice->id) : false,
            'lignes' => $lignes,
            'totaux' => $totaux,
            'parNature' => $this->syntheseParNature($exercice?->id),
            'parStructure' => $this->syntheseParStructure($exercice?->id),
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'libelle']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LigneBudgetaire::class);

        return view('budget.create', $this->options($this->exercice($request)));
    }

    public function store(LigneBudgetaireRequest $request): RedirectResponse
    {
        $this->authorize('create', LigneBudgetaire::class);

        try {
            $this->exerciceService->assertBudgetModifiable((int) $request->exercice_id);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $ligne = LigneBudgetaire::create(collect($request->validated())->except('sources')->all());

        try {
            $this->synchroniserFinancement($request, $ligne);
        } catch (RuntimeException $e) {
            $ligne->delete();

            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $this->rbmBudgetSync->synchroniserLigne($ligne);

        return redirect()->route('budget.index', ['exercice' => $request->exercice_id])
            ->with('succes', 'Ligne budgétaire créée.');
    }

    public function edit(LigneBudgetaire $ligne): View
    {
        $this->authorize('update', $ligne);

        return view('budget.edit', array_merge(['ligne' => $ligne], $this->options($ligne->exercice), [
            'financementSources' => $this->financementService->extrairePourFormulaire($ligne),
        ]));
    }

    public function update(LigneBudgetaireRequest $request, LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('update', $ligne);

        try {
            $this->exerciceService->assertBudgetModifiable($ligne->exercice_id);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $ligne->update(collect($request->validated())->except('sources')->all());

        try {
            $this->synchroniserFinancement($request, $ligne);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $this->rbmBudgetSync->synchroniserLigne($ligne->fresh());

        return redirect()->route('budget.index', ['exercice' => $ligne->exercice_id])
            ->with('succes', 'Ligne budgétaire mise à jour.');
    }

    public function destroy(LigneBudgetaire $ligne): RedirectResponse
    {
        $this->authorize('delete', $ligne);

        try {
            $this->exerciceService->assertBudgetModifiable($ligne->exercice_id);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        if ($raison = $ligne->raisonSuppressionInterdite()) {
            return back()->with('erreur', $raison);
        }

        $exId = $ligne->exercice_id;
        $ligne->delete();

        return redirect()->route('budget.index', ['exercice' => $exId])->with('succes', 'Ligne supprimée.');
    }

    // --- Helpers ---

    private function exercice(Request $request): ?Exercice
    {
        return $request->filled('exercice')
            ? Exercice::find((int) $request->integer('exercice'))
            : ExerciceService::courant();
    }

    private function appliquerFiltresListe(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('structure'), fn ($q) => $q->where('structure_id', (int) $request->integer('structure')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = trim((string) $request->input('q'));

                $q->where(fn ($s) => $s
                    ->where('code', 'like', "%{$terme}%")
                    ->orWhere('intitule', 'like', "%{$terme}%"));
            });
    }

    private function options(?Exercice $exercice): array
    {
        return [
            'exercice' => $exercice,
            'rubriques' => BudgetRubrique::orderBy('code')->get(['id', 'code', 'intitule']),
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'sources' => SourceFinancement::where('actif', true)->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'activites' => \App\Models\Activite::orderBy('code')->limit(500)->get(['id', 'code', 'intitule']),
            'financementSources' => [],
        ];
    }

    private function synchroniserFinancement(LigneBudgetaireRequest $request, LigneBudgetaire $ligne): void
    {
        $sources = $this->normaliserSources($request, $ligne);

        if ($sources === []) {
            return;
        }

        $this->financementService->synchroniser($ligne->fresh(), $sources);
    }

    /** @return array<int,array<string,mixed>> */
    private function normaliserSources(LigneBudgetaireRequest $request, LigneBudgetaire $ligne): array
    {
        $sources = array_values(array_filter($request->input('sources', []), fn ($s) => ! empty($s['source_financement_id'])));

        if ($sources === [] && $request->filled('source_financement_id')) {
            $sources = [[
                'source_financement_id' => (int) $request->source_financement_id,
                'montant' => (float) ($ligne->montant_revise ?? $ligne->montant_ouvert),
                'est_principale' => true,
            ]];
        }

        $principaleIdx = (int) $request->input('source_principale_idx', 0);

        foreach ($sources as $i => &$src) {
            $src['est_principale'] = $i === $principaleIdx;
        }

        return $sources;
    }

    /** Synthèse de consommation par nature (Titre de la nomenclature). */
    private function syntheseParNature(?int $exerciceId)
    {
        return DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->join('budget_paragraphes as pa', 'r.paragraphe_id', '=', 'pa.id')
            ->join('budget_articles as a', 'pa.article_id', '=', 'a.id')
            ->join('budget_titres as t', 'a.titre_id', '=', 't.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->groupBy('t.id', 't.code', 't.intitule', 't.sens')
            ->selectRaw('t.code, t.intitule, t.sens, SUM(lb.montant_ouvert) ouvert, SUM(lb.montant_engage) engage')
            ->orderBy('t.code')->get();
    }

    /** Synthèse de consommation par structure de rattachement. */
    private function syntheseParStructure(?int $exerciceId)
    {
        return DB::table('lignes_budgetaires as lb')
            ->leftJoin('structures as s', 'lb.structure_id', '=', 's.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->groupBy('s.id', 's.libelle')
            ->selectRaw('COALESCE(s.libelle, "(non rattachée)") libelle, SUM(lb.montant_ouvert) ouvert, SUM(lb.montant_engage) engage')
            ->orderByDesc('ouvert')->get();
    }
}
