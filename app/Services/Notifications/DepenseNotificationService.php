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



        $this->dispatcher->envoyer(

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



        $this->dispatcher->envoyer(

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



        $this->dispatcher->envoyer(

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

