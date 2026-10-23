<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\Exercice;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** CRUD enveloppe budgétaire (phase B1). */
class BudgetEnveloppeService
{
    /** @param  array<string,mixed>  $data */
    public function creer(array $data, int $userId): Budget
    {
        return DB::transaction(function () use ($data, $userId) {
            $exercice = Exercice::findOrFail($data['exercice_id']);

            if (Budget::where('exercice_id', $exercice->id)->exists()) {
                throw new RuntimeException("Un budget existe déjà pour l'exercice {$exercice->annee}.");
            }

            $budget = Budget::create([
                'exercice_id' => $exercice->id,
                'reference' => $data['reference'] ?? $this->genererReference($exercice),
                'libelle' => $data['libelle'],
                'structure_id' => $data['structure_id'] ?? null,
                'responsable_id' => $data['responsable_id'] ?? null,
                'date_elaboration' => $data['date_elaboration'] ?? now()->toDateString(),
                'observations' => $data['observations'] ?? null,
                'statut' => 'brouillon',
                'version' => 1,
                'cree_par' => $userId,
                'montant_total' => $this->calculerMontantTotal($exercice->id),
            ]);

            AuditLogger::enregistrer('budget_cree', $budget, null, ['reference' => $budget->reference]);

            return $budget;
        });
    }

    /** @param  array<string,mixed>  $data */
    public function mettreAJour(Budget $budget, array $data): Budget
    {
        if (! $budget->estModifiable() && ! auth()->user()?->hasRole('administrateur')) {
            throw new RuntimeException('Ce budget ne peut plus être modifié.');
        }

        $budget->update([
            'libelle' => $data['libelle'],
            'structure_id' => $data['structure_id'] ?? null,
            'responsable_id' => $data['responsable_id'] ?? null,
            'date_elaboration' => $data['date_elaboration'] ?? $budget->date_elaboration,
            'observations' => $data['observations'] ?? null,
            'montant_total' => $this->calculerMontantTotal($budget->exercice_id),
        ]);

        AuditLogger::enregistrer('budget_modifie', $budget->fresh());

        return $budget->fresh();
    }

    public function supprimer(Budget $budget): void
    {
        if (! $budget->peutEtreSupprime()) {
            throw new RuntimeException('Suppression interdite : budget adopté ou circuit en cours.');
        }

        AuditLogger::enregistrer('budget_supprime', $budget, null, ['reference' => $budget->reference]);
        $budget->delete();
    }

    public function genererReference(Exercice $exercice): string
    {
        return 'BUDGET-'.$exercice->annee;
    }

    public function calculerMontantTotal(int $exerciceId): float
    {
        return (float) \App\Models\LigneBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(montant_revise, montant_ouvert)'));
    }
}
