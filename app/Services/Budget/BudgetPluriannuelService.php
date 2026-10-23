<?php



namespace App\Services\Budget;



use App\Models\Budget;

use App\Models\LigneBudgetaire;

use App\Models\Strategie\ProgrammationAnnuelle;

use App\Models\Strategie\ProgrammationPluriannuelle;

use App\Models\Strategie\ProgrammeStrategique;

use App\Models\Strategie\ProjetStrategique;

use App\Support\AuditLogger;

use RuntimeException;



/** Lien budget ↔ programmation pluriannuelle et rapprochement (phase B4). */

class BudgetPluriannuelService

{

    public function __construct(private readonly BudgetEnveloppeService $enveloppe)

    {

    }



    public function lier(

        Budget $budget,

        int $programmationPluriannuelleId,

        ?int $programmationAnnuelleId,

        int $userId,

    ): Budget {

        $pluri = ProgrammationPluriannuelle::findOrFail($programmationPluriannuelleId);



        if ($programmationAnnuelleId) {

            $annuelle = ProgrammationAnnuelle::where('programmation_pluriannuelle_id', $pluri->id)

                ->where('exercice_id', $budget->exercice_id)

                ->findOrFail($programmationAnnuelleId);

        } else {

            $annuelle = ProgrammationAnnuelle::where('programmation_pluriannuelle_id', $pluri->id)

                ->where('exercice_id', $budget->exercice_id)

                ->first();

        }



        $budget->update([

            'programmation_pluriannuelle_id' => $pluri->id,

            'programmation_annuelle_id' => $annuelle?->id,

        ]);



        AuditLogger::enregistrer('budget_pluri_lie', $budget->fresh(), null, [

            'pluri' => $pluri->reference,

            'annuelle' => $annuelle?->id,

            'par' => $userId,

        ]);



        return $budget->fresh(['programmationPluriannuelle', 'programmationAnnuelle']);

    }



    /** @return array<string,mixed> */

    public function comparer(Budget $budget): array

    {

        $montantBudget = $this->enveloppe->calculerMontantTotal($budget->exercice_id);

        $enveloppePluri = $this->enveloppePluriannuelle($budget);



        $ecart = $montantBudget - $enveloppePluri;



        return [

            'budget_id' => $budget->id,

            'exercice' => $budget->exercice?->annee,

            'montant_budget' => $montantBudget,

            'enveloppe_pluriannuelle' => $enveloppePluri,

            'ecart_absolu' => $ecart,

            'ecart_pct' => $enveloppePluri > 0 ? round(($ecart / $enveloppePluri) * 100, 2) : null,

            'programmation_pluriannuelle' => $budget->programmationPluriannuelle?->only(['id', 'reference', 'annee_debut', 'annee_fin']),

            'programmation_annuelle' => $budget->programmationAnnuelle?->only(['id', 'statut', 'ressources_prevues']),

            'lignes_count' => LigneBudgetaire::where('exercice_id', $budget->exercice_id)->count(),

        ];

    }



    /** @return array<int, array<string,mixed>> */

    public function glissement(Budget $budget): array

    {

        $pluri = $budget->programmationPluriannuelle;

        if (! $pluri) {

            return [];

        }



        $annee = (int) $budget->exercice?->annee;

        $result = [];



        for ($a = $pluri->annee_debut; $a <= $pluri->annee_fin; $a++) {

            $annuelle = ProgrammationAnnuelle::where('programmation_pluriannuelle_id', $pluri->id)

                ->whereHas('exercice', fn ($q) => $q->where('annee', $a))

                ->first();



            $result[] = [

                'annee' => $a,

                'est_exercice_courant' => $a === $annee,

                'programmation_annuelle_id' => $annuelle?->id,

                'statut' => $annuelle?->statut,

                'enveloppe_estimee' => $this->enveloppeEstimeeAnnee($pluri, $a),

            ];

        }



        return $result;

    }



    private function enveloppePluriannuelle(Budget $budget): float

    {

        if ($budget->programmationPluriannuelle) {

            return $this->enveloppeEstimeeAnnee(

                $budget->programmationPluriannuelle,

                (int) $budget->exercice?->annee,

            );

        }



        return (float) ProgrammeStrategique::where('statut', 'actif')->sum('budget_previsionnel')

            + (float) ProjetStrategique::where('statut', 'en_cours')->sum('budget_previsionnel');

    }



    private function enveloppeEstimeeAnnee(ProgrammationPluriannuelle $pluri, int $annee): float

    {

        $totalProgrammes = (float) ProgrammeStrategique::where('statut', 'actif')->sum('budget_previsionnel');

        $annees = max(1, $pluri->annee_fin - $pluri->annee_debut + 1);

        $taux = (float) ($pluri->scenarios['realiste']['taux_croissance'] ?? 4.0);



        $base = $totalProgrammes / $annees;

        $offset = $annee - $pluri->annee_debut;



        return round($base * (1 + ($taux / 100) * $offset), 2);

    }

}

