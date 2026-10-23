<?php



namespace App\Services\Alertes;



use App\Models\Alerte;

use App\Models\User;

use App\Services\Notifications\AuditNotificationService;

use App\Services\Notifications\NotificationDispatcher;

use Illuminate\Support\Collection;



/** Escalade multi-niveaux des alertes critiques (N-P3). */

class AlerteEscaladeService

{

    public function __construct(

        private readonly NotificationDispatcher $notifications,

        private readonly AuditNotificationService $audit,

    ) {}



    public function escalader(Alerte $alerte, ?int $niveauCible = null): int

    {

        if (! $alerte->estOuverte()) {

            return 0;

        }



        if (! in_array($alerte->criticite, config('notifications.escalade.criticites', []), true)) {

            return 0;

        }



        $niveau = $niveauCible ?? min($alerte->escalade_niveau + 1, $this->niveauMax());

        if ($niveau <= $alerte->escalade_niveau) {

            return 0;

        }



        $config = config("notifications.escalade.niveaux.{$niveau}");

        if (! $config) {

            return 0;

        }



        $destinataires = $this->utilisateurs($config['roles'] ?? []);

        if ($destinataires->isEmpty()) {

            return 0;

        }



        $canaux = $config['canaux'] ?? ['database', 'email'];

        $titre = "Escalade n°{$niveau} — {$alerte->titre}";



        $count = $this->notifications->envoyer(

            $destinataires,

            'alerte.institutionnelle',

            $titre,

            $alerte->message,

            route('alertes.index', ['module' => $alerte->module]),

            $alerte->module,

            $alerte->criticite,

            meta: ['alerte_id' => $alerte->id, 'escalade_niveau' => $niveau],

            canaux: $canaux,

        );



        foreach ($destinataires as $user) {

            $this->audit->journaliser(

                'escalade',

                'alerte.institutionnelle',

                $alerte->module,

                'multi',

                destinataire: $user,

                alerteId: $alerte->id,

                escaladeNiveau: $niveau,

                payload: ['alerte_reference' => $alerte->reference],

            );

        }



        $alerte->update([

            'escalade_niveau' => $niveau,

            'derniere_escalade_le' => now(),

        ]);



        return $count;

    }



    public function traiterAutomatique(): int

    {

        $count = 0;

        $delaiHeures = (int) config('notifications.escalade.delai_niveau_heures', 24);



        Alerte::query()

            ->where('statut', 'ouverte')

            ->whereIn('criticite', config('notifications.escalade.criticites', []))

            ->where('escalade_niveau', '<', $this->niveauMax())

            ->where(function ($q) use ($delaiHeures) {

                $q->whereNull('derniere_escalade_le')

                    ->where('detecte_le', '<=', now()->subHours($delaiHeures))

                    ->orWhere('derniere_escalade_le', '<=', now()->subHours($delaiHeures));

            })

            ->each(function (Alerte $alerte) use (&$count) {

                $count += $this->escalader($alerte);

            });



        return $count;

    }



    private function niveauMax(): int

    {

        return count(config('notifications.escalade.niveaux', []));

    }



    /** @param  list<string>  $roles

     * @return Collection<int, User>

     */

    private function utilisateurs(array $roles): Collection

    {

        if ($roles === []) {

            return collect();

        }



        return User::query()

            ->where('actif', true)

            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))

            ->get();

    }

}

