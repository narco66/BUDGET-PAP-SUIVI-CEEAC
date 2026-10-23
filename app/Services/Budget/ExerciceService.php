<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use App\Services\Budget\BudgetReferentielService;
use App\Services\Budget\SituationBudgetaireService;
use App\Services\Pap\PapReferentielService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Gestion métier des exercices budgétaires (ouverture, clôture, verrouillage). */
class ExerciceService
{
    /** Exercice de référence pour les écrans (actif, sinon ouvert, sinon le plus récent). */
    public static function courant(): ?Exercice
    {
        return Exercice::query()
            ->where('est_actif', true)
            ->first()
            ?? Exercice::firstWhere('statut', 'ouvert')
            ?? Exercice::query()->orderByDesc('annee')->first();
    }

    public function estClos(Exercice $exercice): bool
    {
        return $exercice->statut === 'clos';
    }

    public function papVerrouille(int $exerciceId): bool
    {
        $pap = Pap::where('exercice_id', $exerciceId)->first();

        return $pap && in_array($pap->statut, ['en_validation', 'publie'], true);
    }

    /** Budget en validation ou adopté — verrouille les lignes budgétaires. */
    public function budgetVerrouille(int $exerciceId): bool
    {
        $budget = Budget::where('exercice_id', $exerciceId)->first();

        return $budget && in_array($budget->statut, ['en_validation', 'adopte'], true);
    }

    /** Exercice clôturé ou référentiel RBM verrouillé (PAP en validation / publié). */
    public function referentielVerrouille(int $exerciceId): bool
    {
        $exercice = Exercice::find($exerciceId);

        return ! $exercice || $this->estClos($exercice) || $this->papVerrouille($exerciceId);
    }

    /** Lignes budgétaires verrouillées (exercice clos ou budget en validation / adopté). */
    public function budgetLignesVerrouillees(int $exerciceId): bool
    {
        $exercice = Exercice::find($exerciceId);

        return ! $exercice || $this->estClos($exercice) || $this->budgetVerrouille($exerciceId);
    }

    /** Bloque toute écriture budgétaire / financière sur un exercice clôturé. */
    public function assertExerciceOuvert(int $exerciceId): void
    {
        $exercice = Exercice::find($exerciceId);

        if (! $exercice) {
            throw new RuntimeException('Exercice introuvable.');
        }

        if ($this->estClos($exercice)) {
            throw new RuntimeException('Exercice clôturé : aucune écriture budgétaire ou financière n\'est autorisée.');
        }
    }

    /** Bloque la modification du référentiel (RBM, imports PAP) si clos ou PAP verrouillé. */
    public function assertReferentielModifiable(int $exerciceId): void
    {
        $this->assertExerciceOuvert($exerciceId);

        if ($this->papVerrouille($exerciceId)) {
            throw new RuntimeException('Le référentiel RBM est verrouillé : PAP en validation ou publié.');
        }
    }

    /** Bloque la modification des lignes budgétaires si clos ou budget en validation / adopté. */
    public function assertBudgetModifiable(int $exerciceId): void
    {
        $this->assertExerciceOuvert($exerciceId);

        if ($this->budgetVerrouille($exerciceId)) {
            $budget = Budget::where('exercice_id', $exerciceId)->first();
            $statut = $budget?->statut === 'adopte' ? 'adopté' : 'en validation';

            throw new RuntimeException("Le budget est verrouillé : enveloppe {$statut}. Ouvrez une révision pour modifier les lignes.");
        }
    }

    public function ouvrir(Exercice $exercice, User $user): Exercice
    {
        if ($this->estClos($exercice)) {
            throw new RuntimeException('Un exercice clôturé ne peut pas être rouvert.');
        }

        $avant = $exercice->only(['statut']);
        $exercice->update(['statut' => 'ouvert']);
        AuditLogger::enregistrer('exercice_ouvert', $exercice, $avant, ['statut' => 'ouvert', 'par' => $user->id]);

        return $exercice->fresh();
    }

    public function cloturer(Exercice $exercice, User $user): Exercice
    {
        if ($this->estClos($exercice)) {
            throw new RuntimeException('Cet exercice est déjà clôturé.');
        }

        if ($exercice->statut === 'brouillon') {
            throw new RuntimeException('Seul un exercice ouvert peut être clôturé.');
        }

        $avant = $exercice->only(['statut', 'date_cloture']);
        $exercice->update([
            'statut' => 'clos',
            'date_cloture' => now(),
            'cloture_par' => $user->id,
        ]);

        AuditLogger::enregistrer('exercice_cloture', $exercice, $avant, [
            'statut' => 'clos',
            'date_cloture' => $exercice->date_cloture?->toIso8601String(),
            'par' => $user->id,
        ]);

        app(PapReferentielService::class)->cloturerPourExercice($exercice->id, $user->id);
        app(BudgetReferentielService::class)->cloturerPourExercice($exercice->id, $user->id);
        app(SituationBudgetaireService::class)->capturer($exercice->id, $user->id);

        return $exercice->fresh();
    }

    /** Définit l'exercice de référence (un seul actif à la fois). */
    public function definirActif(Exercice $exercice, User $user): Exercice
    {
        DB::transaction(function () use ($exercice, $user) {
            Exercice::where('est_actif', true)->whereKeyNot($exercice->id)->update(['est_actif' => false]);
            $exercice->update(['est_actif' => true]);
            AuditLogger::enregistrer('exercice_actif', $exercice, null, ['est_actif' => true, 'par' => $user->id]);
        });

        return $exercice->fresh();
    }
}
