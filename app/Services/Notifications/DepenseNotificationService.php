<?php

namespace App\Services\Notifications;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Paiement;
use App\Models\User;
use App\Support\NotificationCriticite;
use Illuminate\Support\Collection;

/**
 * Notifications dépenses (besoin, engagement, paiement) — N-P2.
 * Elles informent le service demandeur ; les valideurs sont prévenus à leur étape par
 * WorkflowNotificationService (workflow.etape_en_attente), sans doublon.
 */
class DepenseNotificationService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /** Accusé de soumission au demandeur de l'EB. */
    public function notifierBesoinSoumis(ExpressionBesoin $besoin): void
    {
        $besoin->loadMissing('demandeur', 'exercice');

        $this->envoyerSansAnnuler(
            $this->demandeur($besoin),
            'depense.besoin_soumis',
            'Besoin soumis à validation',
            "Votre expression de besoin {$besoin->numero} ({$besoin->objet}) est soumise et suit son circuit de validation.",
            route('besoins.show', $besoin),
            'depense',
            NotificationCriticite::ATTENTION,
            variables: ['numero' => $besoin->numero, 'objet' => $besoin->objet],
            canaux: $this->canaux(),
            ressource: $besoin,
        );
    }

    /** Engagement soumis au visa : le demandeur est informé de la réservation des crédits. */
    public function notifierEngagementSoumis(Engagement $engagement): void
    {
        $engagement->loadMissing('exercice', 'expressionBesoin.demandeur');

        $this->envoyerSansAnnuler(
            $this->demandeur($engagement->expressionBesoin),
            'depense.engagement_soumis',
            'Engagement soumis à validation',
            "L'engagement {$engagement->numero} ({$engagement->objet}) — ".number_format((float) $engagement->montant_total, 0, ',', ' ').' XAF — est soumis au visa ; les crédits sont réservés.',
            route('engagements.show', $engagement),
            'depense',
            NotificationCriticite::ATTENTION,
            variables: ['numero' => $engagement->numero, 'montant' => $engagement->montant_total],
            canaux: $this->canaux(),
            ressource: $engagement,
        );
    }

    /** Engagement généré depuis une EB approuvée mais non soumis (ex. crédits insuffisants) : le demandeur est averti. */
    public function notifierEngagementNonSoumis(Engagement $engagement, string $motif): void
    {
        $engagement->loadMissing('expressionBesoin.demandeur');

        $this->envoyerSansAnnuler(
            $this->demandeur($engagement->expressionBesoin),
            'depense.engagement_non_soumis',
            'Engagement généré, soumission en attente',
            "L'engagement {$engagement->numero} ({$engagement->objet}) a été généré mais n'a pas pu être soumis automatiquement : {$motif}",
            route('engagements.show', $engagement),
            'depense',
            NotificationCriticite::AVERTISSEMENT,
            variables: ['numero' => $engagement->numero, 'motif' => $motif],
            canaux: $this->canaux(),
            ressource: $engagement,
        );
    }

    /** Liquidation générée au visa de l'engagement : le service initiateur doit certifier le service fait. */
    public function notifierLiquidationGeneree(\App\Models\Liquidation $liquidation): void
    {
        $liquidation->loadMissing('engagement.expressionBesoin.demandeur');
        $engagement = $liquidation->engagement;

        $this->envoyerSansAnnuler(
            $this->demandeur($engagement?->expressionBesoin),
            'depense.liquidation_generee',
            'Liquidation générée : service fait à certifier',
            "L'engagement {$engagement?->numero} est visé. La liquidation {$liquidation->numero} est ouverte : le service initiateur doit certifier le service fait et joindre les pièces (PV, facture, bon de livraison).",
            route('depense.show', ['type' => 'liquidation', 'id' => $liquidation->id]),
            'depense',
            NotificationCriticite::ATTENTION,
            variables: ['numero' => $liquidation->numero],
            canaux: $this->canaux(),
            ressource: $liquidation,
        );
    }

    /** Paiement exécuté : avis au demandeur et au saisisseur du paiement. */
    public function notifierPaiementValide(Paiement $paiement): void
    {
        $paiement->loadMissing('ordonnancement.liquidation.engagement.expressionBesoin.demandeur');
        $ordonnancement = $paiement->ordonnancement;
        $engagement = $ordonnancement?->liquidation?->engagement;
        // Montants réellement enregistrés : seuls les paiements validés comptent comme réglés (RN-17).
        $regle = $ordonnancement ? (float) $ordonnancement->paiements()->regles()->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(montant_xaf, montant)')) : (float) $paiement->montant;
        $solde = $ordonnancement ? max(0, round((float) $ordonnancement->montant - $regle, 2)) : 0.0;
        $fmt = fn (float $m) => number_format($m, 0, ',', ' ').' XAF';
        $destinataires = $this->demandeur($engagement?->expressionBesoin)
            ->merge(User::query()->where('actif', true)->whereKey($paiement->cree_par)->get())
            // Solde restant dû : l'Agence comptable en est informée (référentiel § 6.6).
            ->merge($solde > 0 ? User::query()->where('actif', true)->role('agent_comptable')->get() : collect())
            ->unique('id');

        $this->envoyerSansAnnuler(
            $destinataires,
            'depense.paiement_valide',
            $solde > 0 ? 'Paiement partiel validé' : 'Paiement validé',
            "Paiement {$paiement->numero} de ".$fmt((float) $paiement->montant).($engagement ? " — engagement {$engagement->numero}" : '').' validé. '
                .($ordonnancement
                    ? ($solde > 0
                        ? "Ordonnancement {$ordonnancement->numero} : ".$fmt($regle).' réglés, solde restant dû '.$fmt($solde).'.'
                        : "Ordonnancement {$ordonnancement->numero} intégralement réglé (".$fmt($regle).').')
                    : ''),
            route('depense.show', ['type' => 'paiement', 'id' => $paiement->id]),
            'depense',
            NotificationCriticite::INFORMATION,
            variables: ['numero' => $paiement->numero],
            canaux: $this->canaux(),
            ressource: $paiement,
        );
    }

    /**
     * Ordonnancement signé : le dossier de paiement est prêt, l'Agence comptable doit le préparer (référentiel § 6.5-6.6).
     * Action close dès que le circuit de paiement démarre (WorkflowService::demarrer).
     */
    public function notifierPaiementAPreparer(Paiement $paiement): void
    {
        $paiement->loadMissing('ordonnancement');
        $ord = $paiement->ordonnancement;
        $this->envoyerSansAnnuler(
            User::query()->where('actif', true)->role(['comptable', 'agent_comptable'])->get(),
            'depense.paiement_a_preparer',
            'Ordonnancement signé — paiement à préparer',
            "L'ordonnancement {$ord?->numero} est signé : le paiement {$paiement->numero} de "
                .number_format((float) $paiement->montant, 0, ',', ' ').' XAF est à préparer.',
            route('depense.show', ['type' => 'paiement', 'id' => $paiement->id]),
            'depense',
            NotificationCriticite::ATTENTION,
            variables: ['numero' => $paiement->numero],
            canaux: $this->canaux(),
            ressource: $paiement,
            actionRequise: true,
        );
    }

    /**
     * Une panne de notification ne doit pas annuler une décision financière déjà persistée.
     *
     * @param  Collection<int, User>  $destinataires
     * @param  array<string, scalar|null>  $variables
     */
    private function envoyerSansAnnuler(
        Collection $destinataires,
        string $type,
        string $titre,
        string $message,
        string $url,
        string $module,
        string $criticite,
        array $variables,
        array $canaux,
        ?\Illuminate\Database\Eloquent\Model $ressource = null,
        bool $actionRequise = false,
    ): void {
        if ($destinataires->isEmpty()) {
            return;
        }

        try {
            $this->dispatcher->envoyer(
                $destinataires,
                $type,
                $titre,
                $message,
                $url,
                $module,
                $criticite,
                variables: $variables,
                canaux: $canaux,
                ressource: $ressource,
                actionRequise: $actionRequise,
            );
        } catch (\Throwable $e) {
            report($e);
            try {
                \App\Models\NotificationLog::create([
                    'type_code' => $type,
                    'module' => $module,
                    'canal' => 'reprise',
                    'titre' => $titre,
                    'message' => \Illuminate\Support\Str::limit($e->getMessage(), 500, ''),
                    'url' => $url,
                    'statut' => 'echec_reprise',
                    'metadata' => ['variables' => $variables],
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /** @return list<string> */
    private function canaux(): array
    {
        return config('notifications.email.enabled', true) ? ['database', 'email'] : ['database'];
    }

    /** @return Collection<int, User> */
    private function demandeur(?ExpressionBesoin $besoin): Collection
    {
        $demandeur = $besoin?->demandeur;

        return $demandeur && $demandeur->actif ? collect([$demandeur]) : collect();
    }
}
