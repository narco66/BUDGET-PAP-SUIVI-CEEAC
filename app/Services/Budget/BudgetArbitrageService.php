<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\BudgetArbitrage;
use App\Models\BudgetPreparation;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Arbitrage DPPB et historisation (phase B2). */
class BudgetArbitrageService
{
    public function __construct(private readonly ExerciceService $exercices)
    {
    }

    public function arbitrer(BudgetPreparation $prep, float $montantApres, string $motif, int $userId): BudgetArbitrage
    {
        $budget = $prep->budget ?? Budget::findOrFail($prep->budget_id);
        $this->assertPeutArbitrer($budget, $prep);

        if ($montantApres < 0) {
            throw new RuntimeException('Le montant arbitré ne peut pas être négatif.');
        }

        return DB::transaction(function () use ($prep, $budget, $montantApres, $motif, $userId) {
            $avant = (float) ($prep->montant_propose ?? $prep->montant_demande);

            $arbitrage = BudgetArbitrage::create([
                'budget_id' => $budget->id,
                'budget_preparation_id' => $prep->id,
                'structure_id' => $prep->structure_id,
                'user_id' => $userId,
                'decision' => 'ajustement',
                'montant_avant' => $avant,
                'montant_apres' => $montantApres,
                'motif' => $motif,
            ]);

            $prep->update([
                'montant_arbitre' => $montantApres,
                'montant_propose' => $montantApres,
                'statut' => 'arbitre',
                'valide_le' => now(),
                'valide_par' => $userId,
            ]);

            $ligne = $prep->ligneBudgetaire;
            if ($ligne && (float) $ligne->montant_engage <= 0) {
                $ligne->update([
                    'montant_propose' => $montantApres,
                    'montant_ouvert' => $montantApres,
                ]);
            } elseif ($ligne) {
                $ligne->update(['montant_propose' => $montantApres]);
            }

            AuditLogger::enregistrer('budget_arbitrage', $arbitrage, null, [
                'reference' => $prep->reference,
                'avant' => $avant,
                'apres' => $montantApres,
            ]);

            return $arbitrage;
        });
    }

    /**
     * @param  array<int,array{preparation_id:int,montant_apres:float,motif?:string}>  $decisions
     * @return array{arbitrages:int,ignorees:int}
     */
    public function arbitrerLot(Budget $budget, array $decisions, int $userId): array
    {
        $stats = ['arbitrages' => 0, 'ignorees' => 0];

        foreach ($decisions as $decision) {
            $prep = BudgetPreparation::where('budget_id', $budget->id)
                ->find($decision['preparation_id'] ?? 0);

            if (! $prep || ! in_array($prep->statut, ['en_arbitrage', 'valide_departement'], true)) {
                $stats['ignorees']++;

                continue;
            }

            try {
                $this->arbitrer(
                    $prep,
                    (float) $decision['montant_apres'],
                    $decision['motif'] ?? 'Arbitrage DPPB',
                    $userId,
                );
                $stats['arbitrages']++;
            } catch (RuntimeException) {
                $stats['ignorees']++;
            }
        }

        return $stats;
    }

    /** @return Collection<int, BudgetArbitrage> */
    public function historique(Budget $budget): Collection
    {
        return BudgetArbitrage::query()
            ->where('budget_id', $budget->id)
            ->with(['preparation', 'structure', 'auteur'])
            ->latest()
            ->get();
    }

    private function assertPeutArbitrer(Budget $budget, BudgetPreparation $prep): void
    {
        $this->exercices->assertBudgetModifiable($budget->exercice_id);

        if (! in_array($prep->statut, ['en_arbitrage', 'valide_departement'], true)) {
            throw new RuntimeException('Seules les propositions en arbitrage peuvent être ajustées.');
        }
    }
}
