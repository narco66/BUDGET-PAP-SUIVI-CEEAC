<?php

namespace App\Services\Recettes;

use App\Models\Encaissement;
use App\Models\Recette;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Phase R3 — cycle de vie des encaissements : saisie → validation → rapprochement. */
class EncaissementService
{
    /** Saisie d'un encaissement (statut « saisi », non encore comptabilisé). */
    public function saisir(array $data, User $user): Encaissement
    {
        $recette = Recette::with('exercice')->findOrFail($data['recette_id']);

        if ($recette->exercice && $recette->exercice->estClos()) {
            throw new RuntimeException('Impossible d\'enregistrer un encaissement sur un exercice clos.');
        }

        $montant = (float) $data['montant'];
        $taux = (float) ($data['taux_change'] ?? 1);

        return Encaissement::create([
            'recette_id' => $recette->id,
            'reference' => $data['reference'],
            'montant' => $montant,
            'devise' => $data['devise'] ?? 'XAF',
            'taux_change' => $taux,
            'montant_xaf' => round($montant * $taux, 2),
            'mode_paiement' => $data['mode_paiement'] ?? 'virement',
            'banque' => $data['banque'] ?? null,
            'reference_bancaire' => $data['reference_bancaire'] ?? null,
            'date_encaissement' => $data['date_encaissement'],
            'statut' => 'saisi',
            'saisi_par' => $user->id,
            'observations' => $data['observations'] ?? null,
        ]);
    }

    /** Préconditions soumission workflow (statut saisi/rejeté). */
    public function assertPretPourValidation(Encaissement $encaissement): void
    {
        if (! in_array($encaissement->statut, ['saisi', 'rejete'], true)) {
            throw new RuntimeException('Seul un encaissement saisi ou rejeté peut être soumis.');
        }

        $this->assertJustificatifsEtRubrique($encaissement);
    }

    private function assertJustificatifsEtRubrique(Encaissement $encaissement): void
    {
        if ($encaissement->gedDocuments()->count() === 0 && $encaissement->pieces()->count() === 0) {
            throw new RuntimeException('Soumission impossible : aucun justificatif n\'est rattaché.');
        }

        if (! $encaissement->recette || ! $encaissement->recette->rubrique_id) {
            throw new RuntimeException('Soumission impossible : la recette n\'est pas ventilée (rubrique budgétaire manquante).');
        }
    }

    /**
     * Validation (comptabilisation) : exige au moins un justificatif (contrôle interne)
     * et applique le réalisé sur la recette. Séparation des fonctions : valideur ≠ saisisseur recommandé.
     */
    public function valider(Encaissement $encaissement, User $user): Encaissement
    {
        if (! in_array($encaissement->statut, ['saisi', 'en_validation'], true)) {
            throw new RuntimeException('Seul un encaissement en validation peut être comptabilisé.');
        }

        $this->assertJustificatifsEtRubrique($encaissement);
        return DB::transaction(function () use ($encaissement, $user) {
            $encaissement->update([
                'statut' => 'valide',
                'valide_par' => $user->id,
                'valide_le' => now(),
            ]);

            $encaissement->recette->recalculerRealisation();

            return $encaissement->refresh();
        });
    }

    /** Rapprochement bancaire d'un encaissement validé. */
    public function rapprocher(Encaissement $encaissement, array $data, User $user): Encaissement
    {
        if ($encaissement->statut !== 'valide') {
            throw new RuntimeException('Seul un encaissement validé peut être rapproché.');
        }

        $encaissement->update([
            'statut' => 'rapproche',
            'rapproche' => true,
            'date_rapprochement' => $data['date_rapprochement'],
            'reference_bancaire' => $data['reference_bancaire'] ?? $encaissement->reference_bancaire,
            'rapproche_par' => $user->id,
        ]);

        return $encaissement->refresh();
    }

    /** Annulation : un encaissement rapproché ne s'annule plus ; le réalisé est recalculé. */
    public function annuler(Encaissement $encaissement): Encaissement
    {
        if ($encaissement->statut === 'rapproche') {
            throw new RuntimeException('Un encaissement rapproché ne peut pas être annulé.');
        }

        return DB::transaction(function () use ($encaissement) {
            $encaissement->update(['statut' => 'annule']);
            $encaissement->recette->recalculerRealisation();

            return $encaissement->refresh();
        });
    }
}
