<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\LigneBudgetaire;

/** Snapshot complet du budget à l'adoption (phase B1). */
class BudgetSnapshotService
{
    public function __construct(
        private readonly BudgetAuditService $audit,
        private readonly BudgetEnveloppeService $enveloppe,
    ) {
    }

    /** @return array<string,mixed> */
    public function capturer(Budget $budget): array
    {
        $exerciceId = $budget->exercice_id;

        $lignes = LigneBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->orderBy('code')
            ->get(['id', 'code', 'intitule', 'montant_ouvert', 'montant_revise', 'montant_engage', 'structure_id', 'activite_id']);

        return [
            'budget' => $budget->only([
                'id', 'reference', 'libelle', 'statut', 'version', 'montant_total', 'adopte_le',
            ]),
            'lignes_count' => $lignes->count(),
            'montant_total_calcule' => $this->enveloppe->calculerMontantTotal($exerciceId),
            'lignes' => $lignes->map(fn (LigneBudgetaire $l) => [
                'code' => $l->code,
                'intitule' => $l->intitule,
                'montant_alloue' => (float) $l->montant_alloue,
                'montant_engage' => (float) $l->montant_engage,
            ])->values()->all(),
            'audit' => $this->audit->executer($exerciceId),
        ];
    }
}
