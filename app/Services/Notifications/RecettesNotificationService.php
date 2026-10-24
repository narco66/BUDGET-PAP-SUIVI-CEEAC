<?php



namespace App\Services\Notifications;



use App\Models\AppelFonds;

use App\Models\Encaissement;

use App\Models\Relance;

use App\Models\User;

use App\Support\NotificationCriticite;

use Illuminate\Support\Collection;



/** Notifications recettes (encaissement, relance recouvrement) — N-P2. */

class RecettesNotificationService

{

    public function __construct(

        private readonly NotificationDispatcher $dispatcher,

        private readonly EmailNotificationService $emails,

    ) {}



    public function notifierEncaissementSoumis(Encaissement $encaissement): void

    {

        $encaissement->loadMissing('recette.exercice');

        $destinataires = $this->validateurs('controleur_financier', 'comptable');

        if ($destinataires->isEmpty()) {

            return;

        }



        $this->dispatcher->envoyer(

            $destinataires,

            'recettes.encaissement_soumis',

            'Encaissement à valider',

            "Encaissement {$encaissement->reference} — ".number_format((float) $encaissement->montant_xaf, 0, ',', ' ').' XAF en attente.',

            route('encaissements.show', $encaissement),

            'recettes',

            NotificationCriticite::ATTENTION,

            variables: ['reference' => $encaissement->reference, 'montant' => $encaissement->montant_xaf],

            canaux: $this->canaux(),

        );

    }



    public function envoyerRelanceRecouvrement(Relance $relance): void

    {

        $relance->loadMissing('appel.etatMembre', 'appel.sourceFinancement');

        $appel = $relance->appel;

        if (! $appel) {

            return;

        }



        $variables = [

            'reference' => $appel->reference,

            'objet' => $appel->objet,

            'montant_du' => number_format((float) $relance->montant_du, 0, ',', ' '),

            'destinataire' => $relance->destinataire ?? 'État membre',

            'niveau' => $relance->libelleNiveau(),

        ];



        $sujet = $relance->objet ?? "Relance recouvrement — {$appel->reference}";

        $message = "Relance {$relance->libelleNiveau()} : arriéré de {$variables['montant_du']} XAF sur {$appel->reference} ({$variables['destinataire']}).";



        $destinataires = $this->validateurs('controleur_financier', 'gestionnaire_budgetaire');

        $notificationLogId = null;



        if ($destinataires->isNotEmpty()) {

            $this->dispatcher->envoyer(

                $destinataires,

                'recettes.relance',

                $sujet,

                $message,

                route('appels-fonds.show', $appel),

                'recettes',

                NotificationCriticite::AVERTISSEMENT,

                variables: $variables,

                canaux: ['database'],

            );

        }



        $emailLog = null;

        foreach ($destinataires as $user) {

            if (! $user->email) {

                continue;

            }

            $emailLog = $this->emails->envoyer(

                $user->email,

                'recettes.relance',

                $sujet,

                $message,

                $variables,

                route('appels-fonds.show', $appel),

                $user,

                $relance->destinataire,

                'recettes',

                ['relance_id' => $relance->id, 'appel_fonds_id' => $appel->id],

                $notificationLogId,

                forceSync: app()->runningUnitTests(),

            );

        }



        $relance->update([

            'statut_envoi' => $emailLog?->statut === 'envoye' ? 'envoye' : ($emailLog ? 'en_attente' : 'non_applicable'),

            'email_log_id' => $emailLog?->id,

        ]);

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

