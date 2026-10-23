<?php



namespace App\Services\Workflow;



use App\Models\User;

use App\Models\WorkflowInstance;

use App\Services\Notifications\NotificationDispatcher;

use App\Support\NotificationCriticite;

use App\Support\WorkflowObjetUrls;

use Illuminate\Support\Collection;



/** Notifications transverses workflow : corbeille et relances (P1 → dispatcher N-P0). */

class WorkflowNotificationService

{

    public function __construct(

        private readonly NotificationDispatcher $dispatcher,

    ) {}



    public function notifierEtapeCourante(WorkflowInstance $instance, string $type = 'etape_en_attente'): void

    {

        $instance = $instance->fresh(['workflow', 'objet']);

        $etape = $instance->etapeCourante();

        if (! $etape || $instance->statut !== 'en_cours') {

            return;

        }



        $destinataires = $this->utilisateursParRole($etape->role);

        if ($destinataires->isEmpty()) {

            return;

        }



        $libelle = WorkflowObjetUrls::libelle($instance->objet);

        $circuit = WorkflowObjetUrls::typeLibelle($instance);

        $typeCode = $type === 'relance_sla' ? 'workflow.relance_sla' : 'workflow.etape_en_attente';



        $this->dispatcher->envoyer(

            $destinataires,

            $typeCode,

            "{$circuit} — validation requise",

            "« {$libelle} » attend votre décision (étape {$etape->ordre} · {$etape->role}).",

            WorkflowObjetUrls::show($instance->objet),

            'workflow',

            NotificationCriticite::ATTENTION,

            meta: [

                'workflow_code' => $instance->workflow?->code,

                'instance_id' => $instance->id,

                'etape_ordre' => $etape->ordre,

            ],

        );

    }



    public function notifierRelance(WorkflowInstance $instance, int $joursRetard): void

    {

        $instance = $instance->fresh(['workflow', 'objet']);

        $etape = $instance->etapeCourante();

        if (! $etape) {

            return;

        }



        $destinataires = $this->utilisateursParRole($etape->role);

        $libelle = WorkflowObjetUrls::libelle($instance->objet);



        $this->dispatcher->envoyer(

            $destinataires,

            'workflow.relance_sla',

            'Relance — validation en retard',

            "« {$libelle} » dépasse l'échéance de {$joursRetard} jour(s) (étape {$etape->ordre}).",

            WorkflowObjetUrls::show($instance->objet),

            'workflow',

            NotificationCriticite::AVERTISSEMENT,

            meta: [

                'workflow_code' => $instance->workflow?->code,

                'instance_id' => $instance->id,

                'jours_retard' => $joursRetard,

            ],

        );

    }



    /** @return Collection<int, User> */

    private function utilisateursParRole(string $role): Collection

    {

        return User::query()->where('actif', true)->role($role)->get();

    }

}

