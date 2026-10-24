<?php



namespace App\Services\Notifications;



use App\Models\Engagement;

use App\Models\ExpressionBesoin;

use App\Models\Paiement;

use App\Models\User;

use App\Support\NotificationCriticite;

use Illuminate\Support\Collection;



/** Notifications dépenses (besoin, engagement, paiement) — N-P2. */

class DepenseNotificationService

{

    public function __construct(

        private readonly NotificationDispatcher $dispatcher,

    ) {}



    public function notifierBesoinSoumis(ExpressionBesoin $besoin): void

    {

        $besoin->loadMissing('demandeur', 'exercice');

        $destinataires = $this->validateurs('controleur_financier', 'gestionnaire_budgetaire');

        if ($destinataires->isEmpty()) {

            return;

        }



        $this->envoyerSansAnnuler(

            $destinataires,

            'depense.besoin_soumis',

            'Besoin soumis à validation',

            "L'expression de besoin {$besoin->numero} ({$besoin->objet}) attend validation.",

            route('besoins.show', $besoin),

            'depense',

            NotificationCriticite::ATTENTION,

            variables: ['numero' => $besoin->numero, 'objet' => $besoin->objet],

            canaux: $this->canaux(),

        );

    }



    public function notifierEngagementSoumis(Engagement $engagement): void

    {

        $engagement->loadMissing('exercice', 'createur');

        $destinataires = $this->validateurs('controleur_financier');

        if ($destinataires->isEmpty()) {

            return;

        }



        $this->envoyerSansAnnuler(

            $destinataires,

            'depense.engagement_soumis',

            'Engagement soumis à validation',

            "L'engagement {$engagement->numero} ({$engagement->objet}) — ".number_format((float) $engagement->montant_total, 0, ',', ' ').' XAF.',

            route('engagements.show', $engagement),

            'depense',

            NotificationCriticite::ATTENTION,

            variables: ['numero' => $engagement->numero, 'montant' => $engagement->montant_total],

            canaux: $this->canaux(),

        );

    }



    public function notifierPaiementValide(Paiement $paiement): void

    {

        $paiement->loadMissing('ordonnancement.liquidation.engagement');

        $engagement = $paiement->ordonnancement?->liquidation?->engagement;

        $destinataires = $this->validateurs('controleur_financier', 'comptable', 'commissaire');

        if ($destinataires->isEmpty()) {

            return;

        }



        $this->envoyerSansAnnuler(

            $destinataires,

            'depense.paiement_valide',

            'Paiement validé',

            "Paiement {$paiement->numero}".($engagement ? " — engagement {$engagement->numero}" : '').' validé.',

            route('depense.show', ['type' => 'paiement', 'id' => $paiement->id]),

            'depense',

            NotificationCriticite::INFORMATION,

            variables: ['numero' => $paiement->numero],

            canaux: $this->canaux(),

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
    ): void {
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

    private function validateurs(string ...$roles): Collection

    {

        return User::query()->where('actif', true)->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->get();

    }

}

