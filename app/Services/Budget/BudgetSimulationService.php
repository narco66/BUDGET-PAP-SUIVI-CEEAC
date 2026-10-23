<?php



namespace App\Services\Budget;



use App\Models\Budget;

use App\Models\BudgetConsolidation;

use App\Models\BudgetPreparation;

use App\Models\BudgetSimulation;

use App\Support\AuditLogger;

use Illuminate\Support\Collection;

use RuntimeException;



/** Scénarios budgétaires what-if et comparaison (phase B3). */

class BudgetSimulationService

{

    public function __construct(

        private readonly ExerciceService $exercices,

        private readonly BudgetPreparationService $preparation,

    ) {

    }



    /** @param  array{code?:string,libelle:string,hypothese?:string,type:string,parametres:array<string,mixed>}  $data */

    public function creer(Budget $budget, array $data, int $userId): BudgetSimulation

    {

        $this->assertBudgetSimulable($budget);



        $code = $data['code'] ?? $this->prochainCode($budget);

        $base = $this->lignesBase($budget);



        $sim = BudgetSimulation::create([

            'budget_id' => $budget->id,

            'code' => $code,

            'libelle' => $data['libelle'],

            'hypothese' => $data['hypothese'] ?? null,

            'type' => $data['type'],

            'parametres' => $data['parametres'],

            'montant_base' => (float) $base->sum('montant'),

            'statut' => 'brouillon',

            'cree_par' => $userId,

        ]);



        AuditLogger::enregistrer('budget_sim_cree', $sim, null, ['type' => $sim->type]);



        return $sim;

    }



    public function calculer(BudgetSimulation $sim, ?int $userId = null): BudgetSimulation

    {

        $budget = $sim->budget;

        $this->assertBudgetSimulable($budget);



        $base = $this->lignesBase($budget);

        $resultat = match ($sim->type) {

            'global_pct' => $this->appliquerGlobalPct($base, (float) ($sim->parametres['taux'] ?? 0)),

            'structure_pct' => $this->appliquerStructurePct(

                $base,

                (int) ($sim->parametres['structure_id'] ?? 0),

                (float) ($sim->parametres['taux'] ?? 0),

            ),

            'enveloppe_plafond' => $this->appliquerPlafond($base, (float) ($sim->parametres['plafond'] ?? 0)),

            'ligne_ajustements' => $this->appliquerAjustements($base, $sim->parametres['lignes'] ?? []),

            default => throw new RuntimeException("Type de simulation inconnu : {$sim->type}."),

        };



        $montantBase = (float) $base->sum('montant');

        $montantSimule = (float) collect($resultat['lignes'])->sum('montant_simule');

        $ecart = $montantSimule - $montantBase;



        $sim->update([

            'resultat' => $resultat,

            'montant_base' => $montantBase,

            'montant_simule' => $montantSimule,

            'ecart_absolu' => $ecart,

            'ecart_pct' => $montantBase > 0 ? round(($ecart / $montantBase) * 100, 4) : 0,

            'statut' => 'calcule',

            'calcule_le' => now(),

        ]);



        AuditLogger::enregistrer('budget_sim_calcule', $sim->fresh(), null, [

            'ecart_pct' => $sim->ecart_pct, 'par' => $userId,

        ]);



        return $sim->fresh();

    }



    public function retenir(BudgetSimulation $sim, int $userId): BudgetSimulation

    {

        if (! $sim->estCalculee() && $sim->statut !== 'calcule') {

            if ($sim->statut === 'brouillon') {

                $this->calculer($sim, $userId);

                $sim = $sim->fresh();

            } else {

                throw new RuntimeException('Seule une simulation calculée peut être retenue.');

            }

        }



        BudgetSimulation::where('budget_id', $sim->budget_id)

            ->where('id', '!=', $sim->id)

            ->where('statut', 'retenu')

            ->update(['statut' => 'calcule']);



        $sim->update(['statut' => 'retenu']);

        AuditLogger::enregistrer('budget_sim_retenu', $sim, null, ['par' => $userId]);



        return $sim->fresh();

    }



    /** @param  array<int>  $simulationIds */

    public function comparer(Budget $budget, array $simulationIds): array

    {

        $sims = BudgetSimulation::query()

            ->where('budget_id', $budget->id)

            ->whereIn('id', $simulationIds)

            ->whereIn('statut', ['calcule', 'retenu'])

            ->orderBy('code')

            ->get();



        if ($sims->isEmpty()) {

            throw new RuntimeException('Aucune simulation calculée à comparer.');

        }



        $base = (float) $sims->max('montant_base');



        return [

            'montant_reference' => $base,

            'scenarios' => $sims->map(fn (BudgetSimulation $s) => [

                'id' => $s->id,

                'code' => $s->code,

                'libelle' => $s->libelle,

                'type' => $s->type,

                'hypothese' => $s->hypothese,

                'montant_base' => (float) $s->montant_base,

                'montant_simule' => (float) $s->montant_simule,

                'ecart_absolu' => (float) $s->ecart_absolu,

                'ecart_pct' => (float) $s->ecart_pct,

                'statut' => $s->statut,

                'par_structure' => $s->resultat['par_structure'] ?? [],

            ])->values()->all(),

            'ecart_max' => (float) $sims->max('ecart_absolu'),

            'ecart_min' => (float) $sims->min('ecart_absolu'),

        ];

    }



    /** @return Collection<int, BudgetSimulation> */

    public function simulations(Budget $budget): Collection

    {

        return BudgetSimulation::query()

            ->where('budget_id', $budget->id)

            ->with('createur:id,name')

            ->orderByDesc('updated_at')

            ->get();

    }



    /** @return Collection<int, object> */

    private function lignesBase(Budget $budget): Collection

    {

        $preps = $this->preparation->preparations($budget);



        if ($preps->isNotEmpty()) {

            return $preps->map(fn (BudgetPreparation $p) => (object) [

                'preparation_id' => $p->id,

                'ligne_id' => $p->ligne_budgetaire_id,

                'reference' => $p->reference,

                'structure_id' => $p->structure_id,

                'montant' => $p->montantEffectif(),

            ]);

        }



        return $budget->exercice

            ? \App\Models\LigneBudgetaire::query()

                ->where('exercice_id', $budget->exercice_id)

                ->orderBy('code')

                ->get(['id', 'code', 'structure_id', 'montant_ouvert'])

                ->map(fn ($l) => (object) [

                    'preparation_id' => null,

                    'ligne_id' => $l->id,

                    'reference' => $l->code,

                    'structure_id' => $l->structure_id,

                    'montant' => (float) $l->montant_ouvert,

                ])

            : collect();

    }



    /** @return array<string,mixed> */

    private function appliquerGlobalPct(Collection $base, float $taux): array

    {

        $facteur = 1 + ($taux / 100);



        return $this->construireResultat($base, fn ($l) => max(0, round($l->montant * $facteur, 2)));

    }



    /** @return array<string,mixed> */

    private function appliquerStructurePct(Collection $base, int $structureId, float $taux): array

    {

        if ($structureId <= 0) {

            throw new RuntimeException('Structure requise pour ce scénario.');

        }



        $facteur = 1 + ($taux / 100);



        return $this->construireResultat($base, fn ($l) => (int) $l->structure_id === $structureId

            ? max(0, round($l->montant * $facteur, 2))

            : $l->montant);

    }



    /** @return array<string,mixed> */

    private function appliquerPlafond(Collection $base, float $plafond): array

    {

        $total = (float) $base->sum('montant');

        if ($plafond <= 0 || $total <= 0) {

            throw new RuntimeException('Plafond ou base invalide.');

        }



        $facteur = $plafond / $total;



        return $this->construireResultat($base, fn ($l) => max(0, round($l->montant * $facteur, 2)), $plafond);

    }



    /** @param  array<int,array<string,mixed>>  $ajustements */

    /** @return array<string,mixed> */

    private function appliquerAjustements(Collection $base, array $ajustements): array

    {

        $map = collect($ajustements)->keyBy(fn ($a) => $a['preparation_id'] ?? $a['ligne_id'] ?? 0);



        return $this->construireResultat($base, function ($l) use ($map) {

            $key = $l->preparation_id ?? $l->ligne_id;

            if ($map->has($key)) {

                return max(0, (float) $map[$key]['montant']);

            }



            return $l->montant;

        });

    }



    /** @return array<string,mixed> */

    private function construireResultat(Collection $base, callable $fn, ?float $plafond = null): array

    {

        $lignes = $base->map(function ($l) use ($fn) {

            $simule = (float) $fn($l);



            return [

                'preparation_id' => $l->preparation_id,

                'ligne_id' => $l->ligne_id,

                'reference' => $l->reference,

                'structure_id' => $l->structure_id,

                'montant_base' => (float) $l->montant,

                'montant_simule' => $simule,

                'ecart' => round($simule - $l->montant, 2),

            ];

        })->values()->all();



        $parStructure = collect($lignes)

            ->groupBy('structure_id')

            ->map(fn ($g, $sid) => [

                'structure_id' => $sid,

                'montant_base' => (float) $g->sum('montant_base'),

                'montant_simule' => (float) $g->sum('montant_simule'),

                'ecart' => (float) $g->sum('ecart'),

            ])->values()->all();



        return [

            'lignes' => $lignes,

            'par_structure' => $parStructure,

            'plafond' => $plafond,

        ];

    }



    private function prochainCode(Budget $budget): string

    {

        $n = BudgetSimulation::where('budget_id', $budget->id)->count() + 1;



        return 'SIM-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);

    }



    private function assertBudgetSimulable(Budget $budget): void

    {

        $this->exercices->assertBudgetModifiable($budget->exercice_id);



        if (! $budget->peutPreparer()) {

            throw new RuntimeException('Le budget n\'accepte plus de simulations.');

        }

    }

}

