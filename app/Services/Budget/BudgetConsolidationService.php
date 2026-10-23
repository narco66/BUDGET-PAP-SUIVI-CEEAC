<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\BudgetConsolidation;
use App\Models\BudgetPreparation;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Consolidation des propositions avant adoption (phase B2). */
class BudgetConsolidationService
{
    public function __construct(
        private readonly ExerciceService $exercices,
        private readonly BudgetEnveloppeService $enveloppe,
        private readonly BudgetSnapshotService $snapshots,
    ) {
    }

    /** @return array<string,mixed> */
    public function syntheseGlobale(Budget $budget): array
    {
        $preps = BudgetPreparation::where('budget_id', $budget->id)->get();

        return [
            'lignes' => $preps->count(),
            'montant_demande' => (float) $preps->sum('montant_demande'),
            'montant_propose' => (float) $preps->sum(fn ($p) => (float) ($p->montant_propose ?? $p->montant_demande)),
            'montant_arbitre' => (float) $preps->sum(fn ($p) => (float) ($p->montant_arbitre ?? 0)),
            'par_statut' => $preps->countBy('statut')->all(),
        ];
    }

    /** @return Collection<int, object> */
    public function parStructure(Budget $budget): Collection
    {
        return BudgetPreparation::query()
            ->where('budget_id', $budget->id)
            ->selectRaw('structure_id, COUNT(*) as lignes, SUM(montant_demande) as demande, SUM(COALESCE(montant_arbitre, montant_propose, montant_demande)) as alloue')
            ->groupBy('structure_id')
            ->with('structure:id,libelle,sigle')
            ->get();
    }

    /** @return Collection<int, object> */
    public function parDepartement(Budget $budget): Collection
    {
        $structures = Structure::actif()->whereIn('type', ['departement', 'direction_generale'])->get()->keyBy('id');

        return $this->parStructure($budget)->groupBy(function ($row) use ($structures) {
            $structure = $structures->get($row->structure_id) ?? Structure::find($row->structure_id);

            return $structure?->parent_id ?? $row->structure_id;
        })->map(function ($group, $deptId) {
            $dept = Structure::find($deptId);

            return (object) [
                'departement_id' => $deptId,
                'libelle' => $dept?->libelle ?? 'Non rattaché',
                'lignes' => $group->sum('lignes'),
                'demande' => (float) $group->sum('demande'),
                'alloue' => (float) $group->sum('alloue'),
            ];
        })->values();
    }

    /** Applique les montants arbitrés/consolidés sur les lignes et marque les propositions. */
    public function consolider(Budget $budget, int $userId): array
    {
        $this->exercices->assertBudgetModifiable($budget->exercice_id);

        if (! $budget->estModifiable()) {
            throw new RuntimeException('Consolidation impossible : budget verrouillé.');
        }

        $preps = BudgetPreparation::where('budget_id', $budget->id)
            ->whereIn('statut', ['arbitre', 'en_arbitrage', 'valide_departement'])
            ->get();

        if ($preps->isEmpty()) {
            throw new RuntimeException('Aucune proposition éligible à la consolidation.');
        }

        $stats = ['lignes' => 0, 'montant_total' => 0.0];
        $snapshotAvant = $this->snapshots->capturer($budget);
        $version = (int) BudgetConsolidation::where('budget_id', $budget->id)->max('version') + 1;

        DB::transaction(function () use ($preps, &$stats, $budget, $userId, $snapshotAvant, $version) {
            foreach ($preps as $prep) {
                $montant = (float) ($prep->montant_arbitre ?? $prep->montant_propose ?? $prep->montant_demande);
                $ligne = $prep->ligneBudgetaire;

                if ($ligne && (float) $ligne->montant_engage <= 0) {
                    $ligne->update([
                        'montant_ouvert' => $montant,
                        'montant_propose' => $montant,
                        'budget_id' => $budget->id,
                    ]);
                }

                $prep->update(['statut' => 'consolide', 'valide_le' => now(), 'valide_par' => $userId]);
                $stats['lignes']++;
                $stats['montant_total'] += $montant;
            }

            $budget->update(['montant_total' => $this->enveloppe->calculerMontantTotal($budget->exercice_id)]);

            BudgetConsolidation::create([
                'budget_id' => $budget->id,
                'reference' => $budget->reference.'-CONSOL-v'.$version,
                'version' => $version,
                'snapshot' => $snapshotAvant,
                'resultat' => $stats + ['par_structure' => $this->parStructure($budget)->all()],
                'lignes_count' => $stats['lignes'],
                'montant_total' => $stats['montant_total'],
                'statut' => 'applique',
                'user_id' => $userId,
            ]);
        });

        AuditLogger::enregistrer('budget_consolide', $budget, null, $stats + ['par' => $userId, 'version' => $version]);

        return $stats + ['version' => $version];
    }

    /** @return \Illuminate\Support\Collection<int, BudgetConsolidation> */
    public function historique(Budget $budget): \Illuminate\Support\Collection
    {
        return BudgetConsolidation::query()
            ->where('budget_id', $budget->id)
            ->with('auteur:id,name')
            ->orderByDesc('version')
            ->get();
    }
}
