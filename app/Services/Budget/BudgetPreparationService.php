<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\BudgetPreparation;
use App\Models\LigneBudgetaire;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Initialisation et gestion des propositions budgétaires (phase B2). */
class BudgetPreparationService
{
    public function __construct(private readonly ExerciceService $exercices)
    {
    }

    /** Crée ou met à jour les propositions à partir des lignes de l'exercice. */
    public function initialiser(Budget $budget): int
    {
        $this->exercices->assertBudgetModifiable($budget->exercice_id);

        if (! $budget->estModifiable()) {
            throw new RuntimeException('Le budget n\'est plus en phase de préparation.');
        }

        $count = 0;

        LigneBudgetaire::query()
            ->where('exercice_id', $budget->exercice_id)
            ->orderBy('code')
            ->each(function (LigneBudgetaire $ligne) use ($budget, &$count) {
                $montant = (float) $ligne->montant_ouvert;
                $prep = BudgetPreparation::updateOrCreate(
                    ['budget_id' => $budget->id, 'ligne_budgetaire_id' => $ligne->id],
                    [
                        'structure_id' => $ligne->structure_id,
                        'activite_id' => $ligne->activite_id,
                        'reference' => $ligne->code,
                        'montant_demande' => (float) ($ligne->montant_demande ?? $montant),
                        'montant_propose' => (float) ($ligne->montant_propose ?? $montant),
                        'statut' => 'brouillon',
                    ]
                );

                if ($prep->wasRecentlyCreated) {
                    $count++;
                }

                $ligne->update([
                    'montant_demande' => $prep->montant_demande,
                    'montant_propose' => $prep->montant_propose,
                ]);
            });

        AuditLogger::enregistrer('budget_prep_init', $budget, null, ['creees' => $count]);

        return $count;
    }

    /** @param  array{montant_demande?:float,montant_propose?:float,observations?:string|null}  $data */
    public function mettreAJour(BudgetPreparation $prep, array $data, int $userId): BudgetPreparation
    {
        if (! $prep->estModifiable()) {
            throw new RuntimeException('Cette proposition n\'est plus modifiable.');
        }

        $updates = [];
        if (isset($data['montant_demande'])) {
            $updates['montant_demande'] = max(0, (float) $data['montant_demande']);
        }
        if (isset($data['montant_propose'])) {
            $updates['montant_propose'] = max(0, (float) $data['montant_propose']);
        }
        if (array_key_exists('observations', $data)) {
            $updates['observations'] = $data['observations'];
        }

        $prep->update($updates);

        $ligne = $prep->ligneBudgetaire;
        if ($ligne) {
            $ligne->update(array_filter([
                'montant_demande' => $updates['montant_demande'] ?? null,
                'montant_propose' => $updates['montant_propose'] ?? null,
            ], fn ($v) => $v !== null));
        }

        AuditLogger::enregistrer('budget_preparation_modifiee', $prep, null, $updates + ['par' => $userId]);

        return $prep->fresh();
    }

    /** Soumission par le service pour toutes les propositions d'une structure. */
    public function soumettreStructure(Budget $budget, int $structureId, int $userId): int
    {
        $this->assertBudgetPreparable($budget);

        return $this->transitionStructure($budget, $structureId, 'brouillon', 'soumis_service', $userId, true);
    }

    /** Validation hiérarchique : service → direction → département → DPPB. */
    public function validerNiveau(Budget $budget, string $niveau, int $userId, ?int $structureId = null): int
    {
        $this->assertBudgetPreparable($budget);

        $transitions = BudgetPreparation::TRANSITIONS[$niveau] ?? null;
        if (! $transitions) {
            throw new RuntimeException("Niveau de validation inconnu : {$niveau}.");
        }

        $count = 0;
        foreach ($transitions as $de => $vers) {
            $count += $this->transitionStructure($budget, $structureId, $de, $vers, $userId, false);
        }

        if ($count === 0) {
            throw new RuntimeException('Aucune proposition éligible à cette validation.');
        }

        AuditLogger::enregistrer('budget_preparation_validee', $budget, null, [
            'niveau' => $niveau, 'structure_id' => $structureId, 'count' => $count, 'par' => $userId,
        ]);

        return $count;
    }

    /** Passe les propositions validées département en arbitrage DPPB. */
    public function envoyerArbitrage(Budget $budget, int $userId): int
    {
        return $this->validerNiveau($budget, 'dppb', $userId);
    }

    /** @return Collection<int, BudgetPreparation> */
    public function preparations(Budget $budget, ?int $structureId = null): Collection
    {
        return BudgetPreparation::query()
            ->where('budget_id', $budget->id)
            ->when($structureId, fn ($q) => $q->where('structure_id', $structureId))
            ->with(['ligneBudgetaire', 'structure', 'activite'])
            ->orderBy('reference')
            ->get();
    }

    /** @return array<string,mixed> */
    public function synthese(Budget $budget): array
    {
        $preps = $this->preparations($budget);

        return [
            'total' => $preps->count(),
            'par_statut' => $preps->countBy('statut')->all(),
            'montant_demande' => (float) $preps->sum('montant_demande'),
            'montant_propose' => (float) $preps->sum(fn ($p) => $p->montant_propose ?? $p->montant_demande),
            'montant_arbitre' => (float) $preps->sum(fn ($p) => $p->montant_arbitre ?? 0),
        ];
    }

    private function transitionStructure(
        Budget $budget,
        ?int $structureId,
        string $statutActuel,
        string $nouveauStatut,
        int $userId,
        bool $marquerSoumis,
    ): int {
        $query = BudgetPreparation::query()
            ->where('budget_id', $budget->id)
            ->where('statut', $statutActuel);

        if ($structureId) {
            $query->where('structure_id', $structureId);
        }

        $preps = $query->get();
        $now = now();

        foreach ($preps as $prep) {
            $prep->update([
                'statut' => $nouveauStatut,
                'valide_le' => $now,
                'valide_par' => $userId,
                'soumis_le' => $marquerSoumis ? $now : $prep->soumis_le,
            ]);
        }

        return $preps->count();
    }

    private function assertBudgetPreparable(Budget $budget): void
    {
        $this->exercices->assertBudgetModifiable($budget->exercice_id);

        if (! $budget->peutPreparer()) {
            throw new RuntimeException('Le budget n\'accepte plus de modifications de préparation.');
        }
    }
}
