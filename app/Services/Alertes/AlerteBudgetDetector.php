<?php

namespace App\Services\Alertes;

use App\Models\Budget;
use App\Models\BudgetPreparation;
use App\Models\BudgetRevision;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Support\NotificationCriticite;
use Illuminate\Support\Facades\DB;

/** Détection automatique des alertes budgétaires et financières (N-P1). */
class AlerteBudgetDetector
{
    public function __construct(
        private readonly AlerteRegistryService $registry,
    ) {}

    public function detecter(?int $exerciceId = null): int
    {
        $count = 0;

        $exercices = $exerciceId
            ? Exercice::whereKey($exerciceId)->get()
            : Exercice::where('statut', 'ouvert')->get();

        foreach ($exercices as $exercice) {
            $count += $this->detecterDepassements($exercice);
            $count += $this->detecterInsuffisance($exercice);
            $count += $this->detecterCycleBudgetaire($exercice);
            $count += $this->detecterTresorerie($exercice);
        }

        return $count;
    }

    private function detecterDepassements(Exercice $exercice): int
    {
        $count = 0;

        LigneBudgetaire::query()
            ->where('exercice_id', $exercice->id)
            ->whereNull('deleted_at')
            ->whereColumn('montant_engage', '>', 'montant_ouvert')
            ->each(function (LigneBudgetaire $ligne) use ($exercice, &$count) {
                $this->registry->enregistrer(
                    'budget',
                    'depassement_credit',
                    "Dépassement — {$ligne->code}",
                    "La ligne {$ligne->code} dépasse son crédit ouvert (engagé {$ligne->montant_engage} > ouvert {$ligne->montant_ouvert}).",
                    NotificationCriticite::CRITIQUE,
                    $exercice->id,
                    $ligne,
                );
                $count++;
            });

        return $count;
    }

    private function detecterInsuffisance(Exercice $exercice): int
    {
        $count = 0;

        LigneBudgetaire::query()
            ->where('exercice_id', $exercice->id)
            ->whereNull('deleted_at')
            ->where('montant_ouvert', '>', 0)
            ->get()
            ->each(function (LigneBudgetaire $ligne) use ($exercice, &$count) {
                $disponible = (float) $ligne->montant_ouvert - (float) $ligne->montant_engage;
                $seuil = (float) $ligne->montant_ouvert * 0.1;
                if ($disponible >= 0 && $disponible <= $seuil) {
                    $this->registry->enregistrer(
                        'budget',
                        'insuffisance_credit',
                        "Crédit faible — {$ligne->code}",
                        "Disponible insuffisant sur {$ligne->code} (reste {$disponible} XAF).",
                        NotificationCriticite::AVERTISSEMENT,
                        $exercice->id,
                        $ligne,
                    );
                    $count++;
                }
            });

        return $count;
    }

    private function detecterCycleBudgetaire(Exercice $exercice): int
    {
        $count = 0;
        $budget = Budget::firstWhere('exercice_id', $exercice->id);

        if (! $budget) {
            $this->registry->enregistrer(
                'budget',
                'sans_enveloppe',
                'Budget — enveloppe absente',
                "Aucune enveloppe budgétaire pour l'exercice {$exercice->annee}.",
                NotificationCriticite::AVERTISSEMENT,
                $exercice->id,
            );

            return 1;
        }

        $lignes = (int) LigneBudgetaire::where('exercice_id', $exercice->id)->count();
        $preps = (int) BudgetPreparation::where('budget_id', $budget->id)->count();
        if ($lignes > 0 && $preps === 0 && $budget->estModifiable()) {
            $this->registry->enregistrer(
                'budget',
                'preparations_non_init',
                'Budget — préparations non initiées',
                "Des lignes existent sans préparation budgétaire sur {$budget->reference}.",
                NotificationCriticite::ATTENTION,
                $exercice->id,
                $budget,
            );
            $count++;
        }

        $revisions = (int) BudgetRevision::where('budget_id', $budget->id)->where('statut', 'brouillon')->count();
        if ($revisions > 0) {
            $this->registry->enregistrer(
                'budget',
                'revisions_brouillon',
                'Budget — révisions en attente',
                "{$revisions} révision(s) budgétaire(s) en brouillon sur {$budget->reference}.",
                NotificationCriticite::ATTENTION,
                $exercice->id,
                $budget,
                metadata: ['count' => $revisions],
            );
            $count++;
        }

        return $count;
    }

    private function detecterTresorerie(Exercice $exercice): int
    {
        $restesAPayer = (float) DB::table('paiements as p')
            ->join('ordonnancements as o', 'o.id', '=', 'p.ordonnancement_id')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->where('e.exercice_id', $exercice->id)
            ->where('p.statut', 'valide')
            ->sum('p.montant');

        $restesAEngager = (float) LigneBudgetaire::where('exercice_id', $exercice->id)
            ->whereNull('deleted_at')
            ->get()
            ->sum(fn (LigneBudgetaire $ligne) => max((float) $ligne->montant_ouvert - (float) $ligne->montant_engage, 0));

        $count = 0;

        if ($restesAPayer > 0 && $restesAPayer > $restesAEngager * 0.5) {
            $this->registry->enregistrer(
                'budget',
                'restes_a_payer_eleves',
                'Finances — restes à payer élevés',
                'Volume de paiements validés significatif par rapport aux crédits disponibles.',
                NotificationCriticite::AVERTISSEMENT,
                $exercice->id,
                metadata: ['restes_a_payer' => $restesAPayer, 'disponible' => $restesAEngager],
            );
            $count++;
        }

        return $count;
    }
}
