<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\Budget;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Liste, totaux et CRUD des lignes de crédits (écran Budget et crédits). */
class LigneCreditsService
{
    public function __construct(
        private readonly AccessScopeService $scopes,
        private readonly RbmBudgetSyncService $rbmBudgetSync,
        private readonly ExerciceService $exerciceService,
        private readonly LigneFinancementService $financementService,
    ) {}

    public function exercice(?int $id): ?Exercice
    {
        return $id ? Exercice::find($id) : ExerciceService::courant();
    }

    public function requete(User $user, ?int $exerciceId, Request $request): Builder
    {
        $base = LigneBudgetaire::query()->where('exercice_id', $exerciceId);
        $base = $this->scopes->filtrerStructures($base, $user);

        return $base
            ->when($request->filled('structure'), fn ($q) => $q->where('structure_id', (int) $request->integer('structure')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = trim((string) $request->input('q'));
                $q->where(fn ($s) => $s
                    ->where('code', 'like', "%{$terme}%")
                    ->orWhere('intitule', 'like', "%{$terme}%"));
            });
    }

    public function totaux(Builder $base): object
    {
        return (clone $base)->selectRaw(
            'SUM(montant_ouvert) o, SUM(montant_engage) e, SUM(montant_liquide) l, SUM(montant_ordonnance) ord, SUM(montant_paye) p'
        )->first() ?? (object) ['o' => 0, 'e' => 0, 'l' => 0, 'ord' => 0, 'p' => 0];
    }

    public function syntheseParNature(?int $exerciceId)
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

    public function syntheseParStructure(?int $exerciceId)
    {
        return DB::table('lignes_budgetaires as lb')
            ->leftJoin('structures as s', 'lb.structure_id', '=', 's.id')
            ->where('lb.exercice_id', $exerciceId)
            ->whereNull('lb.deleted_at')
            ->groupBy('s.id', 's.libelle')
            ->selectRaw('COALESCE(s.libelle, \'(non rattachée)\') libelle, SUM(lb.montant_ouvert) ouvert, SUM(lb.montant_engage) engage')
            ->orderByDesc('ouvert')->get();
    }

    /** @return array<string, mixed> */
    public function options(?Exercice $exercice): array
    {
        return [
            'exercice' => $exercice,
            'rubriques' => BudgetRubrique::orderBy('code')->get(['id', 'code', 'intitule']),
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'sigle']),
            'sources' => SourceFinancement::where('actif', true)->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'activites' => Activite::orderBy('code')->limit(500)->get(['id', 'code', 'intitule']),
            'financementSources' => [],
        ];
    }

    /** @param  array<string, mixed>  $donnees */
    public function creer(array $donnees, Request $request): LigneBudgetaire
    {
        $this->exerciceService->assertBudgetModifiable((int) $donnees['exercice_id']);

        $ligne = LigneBudgetaire::create(collect($donnees)->except('sources')->all());

        try {
            $this->synchroniserFinancement($request, $ligne);
        } catch (RuntimeException $e) {
            $ligne->delete();
            throw $e;
        }

        $this->rbmBudgetSync->synchroniserLigne($ligne);

        return $ligne->fresh(['rubrique', 'structure', 'sourceFinancement']) ?? $ligne;
    }

    /** @param  array<string, mixed>  $donnees */
    public function mettreAJour(LigneBudgetaire $ligne, array $donnees, Request $request): LigneBudgetaire
    {
        $this->exerciceService->assertBudgetModifiable($ligne->exercice_id);
        $ligne->update(collect($donnees)->except('sources')->all());
        $this->synchroniserFinancement($request, $ligne);
        $this->rbmBudgetSync->synchroniserLigne($ligne->fresh());

        return $ligne->fresh(['rubrique', 'structure', 'sourceFinancement']) ?? $ligne;
    }

    public function supprimer(LigneBudgetaire $ligne): void
    {
        $this->exerciceService->assertBudgetModifiable($ligne->exercice_id);
        if ($raison = $ligne->raisonSuppressionInterdite()) {
            throw new RuntimeException($raison);
        }
        $ligne->delete();
    }

    public function enveloppe(?int $exerciceId): ?Budget
    {
        return $exerciceId ? Budget::where('exercice_id', $exerciceId)->first() : null;
    }

    public function lignesVerrouillees(?int $exerciceId): bool
    {
        return $exerciceId ? $this->exerciceService->budgetLignesVerrouillees($exerciceId) : false;
    }

    /** @return array<int, array<string, mixed>> */
    public function financementPourFormulaire(LigneBudgetaire $ligne): array
    {
        return $this->financementService->extrairePourFormulaire($ligne);
    }

    private function synchroniserFinancement(Request $request, LigneBudgetaire $ligne): void
    {
        $sources = $this->normaliserSources($request, $ligne);
        if ($sources === []) {
            return;
        }
        $this->financementService->synchroniser($ligne->fresh(), $sources);
    }

    /** @return array<int, array<string, mixed>> */
    private function normaliserSources(Request $request, LigneBudgetaire $ligne): array
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
}
