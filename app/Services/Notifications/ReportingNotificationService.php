<?php



namespace App\Services\Notifications;



use App\Models\Rapport;

use App\Models\User;

use App\Support\NotificationCriticite;

use Illuminate\Support\Collection;



/** Notifications reporting (rapport disponible) — N-P2. */

class ReportingNotificationService

{

    public function __construct(

        private readonly NotificationDispatcher $dispatcher,

    ) {}



    public function notifierRapportGenere(Rapport $rapport, User $auteur): void

    {

        $rapport->loadMissing('exercice');

        $destinataires = $this->validateurs('commissaire', 'controleur_financier', 'secretaire_general')

            ->reject(fn (User $u) => $u->id === $auteur->id);



        if ($destinataires->isEmpty()) {

            return;

        }



        $this->dispatcher->envoyer(

            $destinataires,

            'reporting.rapport_disponible',

            'Rapport disponible',

            "Le rapport {$rapport->reference} ({$rapport->titre}) est disponible.",

            route('reporting.telecharger', $rapport),

            'reporting',

            NotificationCriticite::INFORMATION,

            variables: ['reference' => $rapport->reference, 'titre' => $rapport->titre],

            canaux: config('notifications.email.enabled', true) ? ['database', 'email'] : ['database'],

        );

    }



    /** Notification à l'auteur après génération async (P1-8). */

    public function notifierAuteurRapportPret(Rapport $rapport, User $auteur): void

    {

        $this->dispatcher->envoyer(

            $auteur,

            'reporting.rapport_pret',

            'Votre rapport est prêt',

            "Le rapport {$rapport->reference} ({$rapport->titre}) est disponible au Centre d'exports.",

            route('reporting.telecharger', $rapport),

            'reporting',

            NotificationCriticite::INFORMATION,

            variables: ['reference' => $rapport->reference, 'titre' => $rapport->titre],

            canaux: ['database'],

        );

    }



    public function notifierEchecGeneration(User $auteur, string $type, string $format, string $erreur): void

    {

        $this->dispatcher->envoyer(

            $auteur,

            'reporting.rapport_echec',

            'Échec de génération',

            "La génération du rapport « {$type} » ({$format}) a échoué : {$erreur}",

            route('reporting.historique'),

            'reporting',

            NotificationCriticite::AVERTISSEMENT,

            variables: ['type' => $type, 'format' => $format],

            canaux: ['database'],

        );

    }



    /** @return Collection<int, User> */

    private function validateurs(string ...$roles): Collection

    {

        return User::query()->where('actif', true)->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->get();

    }

}

