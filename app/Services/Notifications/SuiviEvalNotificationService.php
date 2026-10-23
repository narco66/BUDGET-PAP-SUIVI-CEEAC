<?php



namespace App\Services\Notifications;



use App\Models\Recommandation;

use App\Models\User;

use App\Support\NotificationCriticite;

use Illuminate\Support\Collection;



/** Notifications suivi-évaluation (recommandations) — N-P2. */

class SuiviEvalNotificationService

{

    public function __construct(

        private readonly NotificationDispatcher $dispatcher,

    ) {}



    public function notifierRecommandationCreee(Recommandation $recommandation): void

    {

        $recommandation->loadMissing('responsable', 'structure', 'exercice');

        $destinataires = collect();



        if ($recommandation->responsable?->actif) {

            $destinataires->push($recommandation->responsable);

        }



        $destinataires = $destinataires

            ->merge($this->validateurs('directeur', 'chef_service'))

            ->unique('id');



        if ($destinataires->isEmpty()) {

            return;

        }



        $this->dispatcher->envoyer(

            $destinataires,

            'suivi_eval.recommandation',

            'Nouvelle recommandation',

            "Recommandation {$recommandation->numero} : {$recommandation->libelle}",

            route('recommandations.show', $recommandation),

            'suivi_eval',

            NotificationCriticite::ATTENTION,

            variables: ['numero' => $recommandation->numero, 'libelle' => $recommandation->libelle],

            canaux: config('notifications.email.enabled', true) ? ['database', 'email'] : ['database'],

        );

    }



    /** @return Collection<int, User> */

    private function validateurs(string ...$roles): Collection

    {

        return User::query()->where('actif', true)->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->get();

    }

}

