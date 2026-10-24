<?php

namespace App\Services\Budget;

use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Workflow brouillon → soumis → validé / rejeté pour les lignes du module Structure du Budget. */
class StructureBudgetValidationService
{
    public function __construct(
        private readonly StructureBudgetCoherenceService $coherence,
    ) {}

    public function soumettre(LigneBudgetaire $ligne, User $user): LigneBudgetaire
    {
        return DB::transaction(function () use ($ligne, $user) {
            $ligne = $this->verrouiller($ligne);

            if (! in_array($ligne->statut_validation, ['brouillon', 'rejete'], true)) {
                throw new RuntimeException('Seule une ligne en brouillon ou rejetée peut être soumise.');
            }

            $this->assertPretPourSoumission($ligne);

            $avant = ['statut_validation' => $ligne->statut_validation];
            $ligne->update([
                'statut_validation' => 'soumis',
                'soumis_le' => now(),
                'motif_rejet' => null,
            ]);

            AuditLogger::enregistrer('structure_budget_ligne_soumise', $ligne, $avant, [
                'statut_validation' => 'soumis',
                'soumis_par' => $user->id,
            ]);

            return $ligne->fresh();
        });
    }

    public function valider(LigneBudgetaire $ligne, User $user): LigneBudgetaire
    {
        return DB::transaction(function () use ($ligne, $user) {
            $ligne = $this->verrouiller($ligne);

            if ($ligne->statut_validation !== 'soumis') {
                throw new RuntimeException('Seule une ligne soumise peut être validée.');
            }

            $this->assertPretPourSoumission($ligne);

            $avant = ['statut_validation' => $ligne->statut_validation];
            $ligne->update([
                'statut_validation' => 'valide',
                'valide_le' => now(),
                'valide_par' => $user->id,
                'motif_rejet' => null,
            ]);

            AuditLogger::enregistrer('structure_budget_ligne_validee', $ligne, $avant, [
                'statut_validation' => 'valide',
                'valide_par' => $user->id,
            ]);

            return $ligne->fresh();
        });
    }

    public function rejeter(LigneBudgetaire $ligne, User $user, string $motif): LigneBudgetaire
    {
        return DB::transaction(function () use ($ligne, $user, $motif) {
            $ligne = $this->verrouiller($ligne);

            if ($ligne->statut_validation !== 'soumis') {
                throw new RuntimeException('Seule une ligne soumise peut être rejetée.');
            }

            $motif = trim($motif);
            if ($motif === '') {
                throw new RuntimeException('Le motif de rejet est obligatoire.');
            }

            $avant = ['statut_validation' => $ligne->statut_validation];
            $ligne->update([
                'statut_validation' => 'rejete',
                'motif_rejet' => $motif,
                'valide_le' => null,
                'valide_par' => null,
            ]);

            AuditLogger::enregistrer('structure_budget_ligne_rejetee', $ligne, $avant, [
                'statut_validation' => 'rejete',
                'motif_rejet' => $motif,
                'rejete_par' => $user->id,
            ]);

            return $ligne->fresh();
        });
    }

    public function assertModifiable(LigneBudgetaire $ligne): void
    {
        if (! $ligne->estModifiable()) {
            throw new RuntimeException(
                'Cette ligne est en attente de validation ou déjà validée — modification impossible sans rejet préalable.'
            );
        }
    }

    private function verrouiller(LigneBudgetaire $ligne): LigneBudgetaire
    {
        return LigneBudgetaire::query()
            ->whereKey($ligne->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertPretPourSoumission(LigneBudgetaire $ligne): void
    {
        if (! $ligne->structure_id) {
            throw new RuntimeException('Structure institutionnelle obligatoire avant soumission.');
        }

        if ((float) $ligne->montant_ouvert <= 0) {
            throw new RuntimeException('Le montant ouvert doit être strictement positif.');
        }

        if (! $this->coherence->rubriqueEstValide($ligne->rubrique_id)) {
            throw new RuntimeException('La rubrique budgétaire doit être validée dans la nomenclature.');
        }
    }
}
