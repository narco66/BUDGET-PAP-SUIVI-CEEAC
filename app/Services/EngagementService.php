<?php

namespace App\Services;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Services\Budget\ExerciceService;
use App\Support\AuditLogger;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EngagementService
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly ExerciceService $exercices,
    ) {
    }

    /**
     * Crée un engagement (brouillon) avec ses imputations multiples.
     * @param array<int,array{ligne_budgetaire_id:int,montant:float}> $imputations
     */
    public function creer(array $data, array $imputations, User $user): Engagement
    {
        $imputations = array_values(array_filter($imputations, fn ($i) => (float) ($i['montant'] ?? 0) > 0));
        if ($imputations === []) {
            throw new RuntimeException('Au moins une imputation budgétaire est requise.');
        }

        $this->exercices->assertExerciceOuvert((int) $data['exercice_id']);
        $this->assertBesoinRequis($data, $user);
        $this->assertBesoinNonDejaEngage($data);

        $besoin = isset($data['expression_besoin_id'])
            ? ExpressionBesoin::find($data['expression_besoin_id'])
            : null;

        if ($besoin && ! isset($data['activite_id'])) {
            $data['activite_id'] = $besoin->activite_id;
        }

        if ($besoin && ! isset($data['tache_id']) && $besoin->tache_id) {
            $data['tache_id'] = $besoin->tache_id;
        }

        $annee = Exercice::find($data['exercice_id'])?->annee ?? (int) date('Y');
        $total = array_sum(array_map(fn ($i) => (float) $i['montant'], $imputations));

        return DB::transaction(function () use ($data, $imputations, $user, $annee, $total) {
            $engagement = Engagement::create([
                ...$data,
                'numero' => Numero::suivant('engagements', 'ENG', $annee),
                'montant_total' => $total,
                'statut' => 'brouillon',
                'cree_par' => $user->id,
                'credits_reserves' => false,
            ]);

            foreach ($imputations as $imp) {
                $engagement->imputations()->create([
                    'ligne_budgetaire_id' => $imp['ligne_budgetaire_id'],
                    'montant' => (float) $imp['montant'],
                ]);
            }

            return $engagement;
        });
    }

    /** @param array<string,mixed> $data */
    private function assertBesoinRequis(array $data, User $user): void
    {
        if ($user->can('depenses.admin')) {
            return;
        }

        $besoinId = $data['expression_besoin_id'] ?? null;
        if (! $besoinId) {
            throw new RuntimeException('Un besoin validé est requis pour créer un engagement.');
        }

        $besoin = ExpressionBesoin::find($besoinId);
        if (! $besoin || ! in_array($besoin->statut, ['valide', 'converti'], true)) {
            throw new RuntimeException('Le besoin doit être validé avant engagement.');
        }

        if ($besoin->estPap() && ! $besoin->activite_id) {
            throw new RuntimeException('Le besoin PAP doit être rattaché à une activité programmée.');
        }

        if (! $besoin->ligne_budgetaire_id) {
            throw new RuntimeException('Le besoin doit être rattaché à une ligne budgétaire.');
        }
    }

    /** @param array<string,mixed> $data */
    private function assertBesoinNonDejaEngage(array $data): void
    {
        $besoinId = $data['expression_besoin_id'] ?? null;
        if (! $besoinId) {
            return;
        }

        if (Engagement::query()->where('expression_besoin_id', $besoinId)->exists()) {
            throw new RuntimeException('Un engagement existe déjà pour ce besoin.');
        }
    }

    public function creerDepuisBesoinApprouve(ExpressionBesoin $besoin, User $user): Engagement
    {
        $existant = Engagement::query()->where('expression_besoin_id', $besoin->id)->first();
        if ($existant) {
            return $existant;
        }

        return $this->creer(
            [
                'exercice_id' => $besoin->exercice_id,
                'expression_besoin_id' => $besoin->id,
                'activite_id' => $besoin->activite_id,
                'tache_id' => $besoin->tache_id,
                'type' => 'simple',
                'objet' => $besoin->objet,
            ],
            [[
                'ligne_budgetaire_id' => (int) $besoin->ligne_budgetaire_id,
                'montant' => (float) $besoin->montant_estime,
            ]],
            $user,
        );
    }

    /**
     * Soumet l'engagement : réserve les crédits (contrôle de disponibilité) puis
     * démarre le circuit de visa du contrôleur financier.
     */
    public function soumettre(Engagement $engagement, User $user): void
    {
        if (! $engagement->estModifiable()) {
            throw new RuntimeException('Cet engagement ne peut plus être soumis.');
        }

        $this->exercices->assertExerciceOuvert($engagement->exercice_id);

        try {
            $engagement->reserverCredits();
        } catch (RuntimeException $e) {
            $this->enregistrerInsuffisance($engagement);
            throw $e;
        }
        app(\App\Services\Depense\CertificatEngagementService::class)
            ->enregistrer($engagement, 'reservation', $user);
        $this->workflow->demarrer($engagement, 'ENGAGEMENT', $user);

        app(\App\Services\Notifications\DepenseNotificationService::class)
            ->notifierEngagementSoumis($engagement->fresh());

        AuditLogger::enregistrer('engagement_soumis', $engagement, null, ['montant' => (float) $engagement->montant_total]);
    }

    private function enregistrerInsuffisance(Engagement $engagement): void
    {
        $engagement->load('imputations.ligneBudgetaire');
        foreach ($engagement->imputations as $imp) {
            $ligne = $imp->ligneBudgetaire;
            if ($ligne && ! $ligne->peutEngager((float) $imp->montant)) {
                app(\App\Services\Depense\InsuffisanceCreditService::class)
                    ->constater($engagement, $ligne, (float) $imp->montant);

                return;
            }
        }
    }

    /** Annule un engagement et libère les crédits éventuellement réservés. */
    public function annuler(Engagement $engagement, User $user): void
    {
        if ($engagement->liquidations()->whereNotIn('statut', ['rejete', 'annule', 'brouillon'])->exists()) {
            throw new RuntimeException('Annulation impossible : des liquidations visées existent. Dégager uniquement le reliquat non liquidé.');
        }

        $engagement->liquidations()->where('statut', 'brouillon')->update(['statut' => 'annule']);
        $engagement->libererCredits();
        $engagement->update(['statut' => 'annule']);
        AuditLogger::enregistrer('engagement_annule', $engagement);
    }

    /** Réduit l’engagement visé sans descendre sous le montant déjà liquidé. */
    public function degager(Engagement $engagement, float $montant, User $user): void
    {
        if ($montant <= 0) {
            throw new RuntimeException('Le montant à dégager doit être strictement positif.');
        }
        if (! in_array($engagement->statut, ['vise', 'soumis'], true)) {
            throw new RuntimeException('Seuls les engagements soumis ou visés peuvent être dégagés partiellement.');
        }

        $liquide = (float) $engagement->liquidations()->where('statut', 'valide')->sum('montant');
        $plafond = round((float) $engagement->montant_total - $liquide, 2);
        if ($montant > $plafond + 0.01) {
            throw new RuntimeException(
                'Dégagement plafonné au reliquat non liquidé ('.number_format($plafond, 0, ',', ' ').' XAF).'
            );
        }

        DB::transaction(function () use ($engagement, $montant) {
            $reste = $montant;
            foreach ($engagement->imputations()->orderByDesc('id')->get() as $imp) {
                $prise = min((float) $imp->montant, $reste);
                if ($prise <= 0) {
                    continue;
                }
                if ($engagement->credits_reserves) {
                    LigneBudgetaire::whereKey($imp->ligne_budgetaire_id)->decrement('montant_engage', $prise);
                    // La réservation active suit l'imputation : elle restait au montant initial après dégagement.
                    $this->reduireReservation($engagement, (int) $imp->ligne_budgetaire_id, $prise);
                }
                $nouveau = round((float) $imp->montant - $prise, 2);
                if ($nouveau <= 0.005) {
                    $imp->delete();
                } else {
                    $imp->update(['montant' => $nouveau]);
                }
                $reste = round($reste - $prise, 2);
            }

            $engagement->update([
                'montant_total' => round((float) $engagement->montant_total - $montant, 2),
            ]);
        });

        AuditLogger::enregistrer('engagement_degage', $engagement, null, ['montant' => $montant, 'par' => $user->id]);
        app(\App\Services\Depense\ChaineClotureService::class)->reouvrirSiNecessaire($engagement->fresh());
    }

    /** Réduit les réservations actives d'une ligne du montant dégagé ; une réservation soldée est libérée. */
    private function reduireReservation(Engagement $engagement, int $ligneId, float $montant): void
    {
        $reste = $montant;
        foreach ($engagement->reservationsCredits()->where('ligne_budgetaire_id', $ligneId)->where('statut', 'active')->orderByDesc('id')->lockForUpdate()->get() as $reservation) {
            if ($reste <= 0.005) {
                break;
            }
            $prise = min((float) $reservation->montant, $reste);
            $nouveau = round((float) $reservation->montant - $prise, 2);
            $reservation->update($nouveau <= 0.005
                ? ['montant' => 0, 'statut' => 'liberee', 'liberee_le' => now()]
                : ['montant' => $nouveau]);
            $reste = round($reste - $prise, 2);
        }
    }
}
