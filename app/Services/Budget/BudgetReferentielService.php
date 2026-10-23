<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\BudgetHistorique;
use App\Models\LigneBudgetaire;
use App\Support\AuditLogger;
use RuntimeException;

/** Règles métier transverses budget (verrouillage, historisation, adoption). */
class BudgetReferentielService
{
    public function __construct(
        private readonly BudgetAuditService $audit,
        private readonly BudgetSnapshotService $snapshot,
        private readonly BudgetEnveloppeService $enveloppe,
    ) {
    }

    /** Snapshot avant soumission, révision ou adoption. */
    public function historiser(Budget $budget, string $action, ?int $userId = null, ?string $motif = null): BudgetHistorique
    {
        $complet = $action === 'adoption';

        $snapshot = $complet
            ? $this->snapshot->capturer($budget)
            : [
                'statut' => $budget->statut,
                'montant_total' => $this->enveloppe->calculerMontantTotal($budget->exercice_id),
                'audit' => $this->audit->executer($budget->exercice_id)['anomalies_total'],
            ];

        $hist = BudgetHistorique::create([
            'budget_id' => $budget->id,
            'version' => $budget->version,
            'action' => $action,
            'snapshot' => $snapshot,
            'snapshot_complet' => $complet,
            'motif' => $motif,
            'user_id' => $userId,
        ]);

        AuditLogger::enregistrer("budget_{$action}", $budget, null, $snapshot);

        return $hist;
    }

    /** Rattache les lignes à l'enveloppe adoptée et fixe la date d'entrée en vigueur. */
    public function rattacherLignes(Budget $budget): int
    {
        $date = ($budget->adopte_le ?? now())->toDateString();

        return LigneBudgetaire::query()
            ->where('exercice_id', $budget->exercice_id)
            ->update([
                'budget_id' => $budget->id,
                'date_entree_vigueur' => $date,
            ]);
    }

    /** Ouvre une révision post-adoption (déverrouille les lignes budgétaires). */
    public function demanderRevision(Budget $budget, int $userId, string $motif): BudgetHistorique
    {
        if (! $budget->peutDemanderRevision()) {
            throw new RuntimeException('Seul un budget adopté peut être ouvert en révision.');
        }

        $hist = $this->historiser($budget, 'ouverture_revision', $userId, $motif);
        $budget->update(['statut' => 'en_revision']);

        return $hist;
    }

    /** Clôture automatique du budget lors de la clôture de l'exercice. */
    public function cloturerPourExercice(int $exerciceId, ?int $userId = null): int
    {
        $budgets = Budget::where('exercice_id', $exerciceId)
            ->whereNotIn('statut', ['clos', 'brouillon'])
            ->get();

        foreach ($budgets as $budget) {
            $budget->update(['statut' => 'clos']);
            $this->historiser($budget, 'cloture_exercice', $userId, 'Clôture automatique à la clôture de l\'exercice.');
        }

        return $budgets->count();
    }
}
