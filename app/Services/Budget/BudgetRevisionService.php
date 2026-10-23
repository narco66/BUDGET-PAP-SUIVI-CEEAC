<?php



namespace App\Services\Budget;



use App\Models\Budget;

use App\Models\BudgetRevision;

use App\Models\BudgetRevisionMouvement;

use App\Models\LigneBudgetaire;

use App\Support\AuditLogger;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use RuntimeException;



/** Rectificatifs et virements budgétaires tracés (phase B4). */

class BudgetRevisionService

{

    public function __construct(

        private readonly ExerciceService $exercices,

        private readonly BudgetEnveloppeService $enveloppe,

        private readonly BudgetReferentielService $referentiel,

    ) {

    }



    public function creerRectificatif(

        Budget $budget,

        int $ligneId,

        float $montantApres,

        string $motif,

        int $userId,

    ): BudgetRevision {

        $this->assertBudgetRectifiable($budget);



        $ligne = $this->ligneDuBudget($budget, $ligneId);

        $avant = $ligne->montant_alloue;

        $delta = $montantApres - $avant;



        if ($delta === 0.0) {

            throw new RuntimeException('Le montant rectifié est identique au montant actuel.');

        }



        $this->assertMontantAutorise($ligne, $montantApres);



        return DB::transaction(function () use ($budget, $ligne, $avant, $montantApres, $delta, $motif, $userId) {

            $revision = $this->nouvelleRevision($budget, 'rectificatif', $motif, abs($delta), $userId);



            BudgetRevisionMouvement::create([

                'budget_revision_id' => $revision->id,

                'ligne_budgetaire_id' => $ligne->id,

                'role' => 'cible',

                'montant_avant' => $avant,

                'montant_apres' => $montantApres,

                'delta' => $delta,

            ]);



            AuditLogger::enregistrer('budget_rev_cree', $revision, null, ['type' => 'rectificatif']);



            return $revision->load('mouvements');

        });

    }



    public function creerVirement(

        Budget $budget,

        int $ligneSourceId,

        int $ligneDestId,

        float $montant,

        string $motif,

        int $userId,

    ): BudgetRevision {

        $this->assertBudgetRectifiable($budget);



        if ($ligneSourceId === $ligneDestId) {

            throw new RuntimeException('Les lignes source et destination doivent être distinctes.');

        }



        if ($montant <= 0) {

            throw new RuntimeException('Le montant du virement doit être strictement positif.');

        }



        $source = $this->ligneDuBudget($budget, $ligneSourceId);

        $dest = $this->ligneDuBudget($budget, $ligneDestId);



        $avantSource = $source->montant_alloue;

        $avantDest = $dest->montant_alloue;

        $apresSource = $avantSource - $montant;

        $apresDest = $avantDest + $montant;



        $this->assertMontantAutorise($source, $apresSource);



        return DB::transaction(function () use (

            $budget, $source, $dest, $avantSource, $avantDest, $apresSource, $apresDest, $montant, $motif, $userId

        ) {

            $revision = $this->nouvelleRevision($budget, 'virement', $motif, $montant, $userId);



            BudgetRevisionMouvement::create([

                'budget_revision_id' => $revision->id,

                'ligne_budgetaire_id' => $source->id,

                'ligne_destination_id' => $dest->id,

                'role' => 'source',

                'montant_avant' => $avantSource,

                'montant_apres' => $apresSource,

                'delta' => -$montant,

            ]);



            BudgetRevisionMouvement::create([

                'budget_revision_id' => $revision->id,

                'ligne_budgetaire_id' => $dest->id,

                'ligne_destination_id' => $source->id,

                'role' => 'destination',

                'montant_avant' => $avantDest,

                'montant_apres' => $apresDest,

                'delta' => $montant,

            ]);



            AuditLogger::enregistrer('budget_rev_cree', $revision, null, ['type' => 'virement']);



            return $revision->load('mouvements');

        });

    }



    public function valider(BudgetRevision $revision, int $userId): BudgetRevision

    {

        if (! $revision->estModifiable()) {

            throw new RuntimeException('Cette révision n\'est plus modifiable.');

        }



        $budget = $revision->budget;

        $this->assertBudgetRectifiable($budget);



        DB::transaction(function () use ($revision, $budget, $userId) {

            foreach ($revision->mouvements as $mvt) {

                $ligne = $mvt->ligneBudgetaire;

                $this->assertMontantAutorise($ligne, (float) $mvt->montant_apres);

                $this->appliquerMontant($budget, $ligne, (float) $mvt->montant_apres);

            }



            $revision->update([

                'statut' => 'valide',

                'valide_le' => now(),

                'valide_par' => $userId,

            ]);



            $budget->update([

                'montant_total' => $this->enveloppe->calculerMontantTotal($budget->exercice_id),

            ]);



            $this->referentiel->historiser($budget, 'revision_'.$revision->type, $userId, $revision->motif);

        });



        AuditLogger::enregistrer('budget_rev_valide', $revision->fresh(), null, ['par' => $userId]);

        $revision = $revision->fresh(['mouvements.ligneBudgetaire', 'validateur']);
        app(\App\Services\WebhookService::class)->declencher('budget.revision_validee', [
            'reference' => $revision->reference,
            'type' => $revision->type,
            'budget_id' => $revision->budget_id,
        ]);

        return $revision;

    }



    public function annuler(BudgetRevision $revision, int $userId): BudgetRevision

    {

        if (! $revision->estModifiable()) {

            throw new RuntimeException('Seule une révision en brouillon peut être annulée.');

        }



        $revision->update(['statut' => 'annule']);

        AuditLogger::enregistrer('budget_rev_annule', $revision, null, ['par' => $userId]);



        return $revision->fresh();

    }



    /** @return Collection<int, BudgetRevision> */

    public function historique(Budget $budget): Collection

    {

        return BudgetRevision::query()

            ->where('budget_id', $budget->id)

            ->with(['mouvements.ligneBudgetaire', 'validateur:id,name', 'createur:id,name'])

            ->orderByDesc('created_at')

            ->get();

    }



    private function nouvelleRevision(Budget $budget, string $type, string $motif, float $montantTotal, int $userId): BudgetRevision

    {

        $num = BudgetRevision::where('budget_id', $budget->id)->count() + 1;



        return BudgetRevision::create([

            'budget_id' => $budget->id,

            'reference' => $budget->reference.'-REV-'.str_pad((string) $num, 3, '0', STR_PAD_LEFT),

            'type' => $type,

            'motif' => $motif,

            'montant_total' => $montantTotal,

            'statut' => 'brouillon',

            'programmation_pluriannuelle_id' => $budget->programmation_pluriannuelle_id,

            'programmation_annuelle_id' => $budget->programmation_annuelle_id,

            'cree_par' => $userId,

        ]);

    }



    private function appliquerMontant(Budget $budget, LigneBudgetaire $ligne, float $montant): void

    {

        if ($budget->statut === 'adopte') {

            $ligne->update(['montant_revise' => $montant]);

        } else {

            $ligne->update(['montant_ouvert' => $montant, 'montant_revise' => null]);

        }

    }



    private function assertMontantAutorise(LigneBudgetaire $ligne, float $montant): void

    {

        if ($montant < 0) {

            throw new RuntimeException('Le montant ne peut pas être négatif.');

        }



        if ($montant < (float) $ligne->montant_engage) {

            throw new RuntimeException(

                "Montant insuffisant sur la ligne {$ligne->code} : crédits engagés supérieurs au nouveau montant."

            );

        }

    }



    private function ligneDuBudget(Budget $budget, int $ligneId): LigneBudgetaire

    {

        $ligne = LigneBudgetaire::where('exercice_id', $budget->exercice_id)->find($ligneId);



        if (! $ligne) {

            throw new RuntimeException('Ligne budgétaire introuvable pour cet exercice.');

        }



        return $ligne;

    }



    private function assertBudgetRectifiable(Budget $budget): void

    {

        $this->exercices->assertExerciceOuvert($budget->exercice_id);



        if (! $budget->peutRectifier()) {

            throw new RuntimeException('Les rectificatifs/virements ne sont possibles que sur un budget adopté ou en révision.');

        }

    }

}

