<?php



namespace App\Services\Notifications;



use App\Models\NotificationLog;

use App\Models\NotificationTemplate;

use App\Models\NotificationType;

use App\Models\User;

use App\Notifications\InstitutionInAppNotification;

use App\Support\NotificationCriticite;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Notification;

use RuntimeException;



/** Point d'entrée centralisé — in-app, e-mail, SMS, audit (N-P0 → P3). */

class NotificationDispatcher

{

    public function __construct(

        private readonly EmailNotificationService $emails,

        private readonly SmsNotificationService $sms,

        private readonly NotificationPreferenceService $preferences,

        private readonly AuditNotificationService $audit,

    ) {}



    /**

     * @param  Collection<int, User>|User|iterable<User>  $destinataires

     * @param  array<string, scalar|null>  $variables

     * @param  array<string, mixed>  $meta

     */

    public function envoyer(

        Collection|User|iterable $destinataires,

        string $typeCode,

        string $titre,

        string $message,

        ?string $url = null,

        ?string $module = null,

        string $criticite = NotificationCriticite::INFORMATION,

        array $variables = [],

        array $meta = [],

        ?User $emetteur = null,

        string $canal = 'database',

        array $canaux = ['database'],

    ): int {

        if (! $this->autoriseEnvoi($emetteur)) {

            throw new RuntimeException('Permission notifications.send requise pour émettre une notification.');

        }



        $type = NotificationType::where('code', $typeCode)->where('actif', true)->first();

        $module ??= $type?->module ?? 'systeme';

        $criticite = $type?->criticite_defaut ?? $criticite;



        $template = $type

            ? NotificationTemplate::where('notification_type_id', $type->id)

                ->where('canal', $canal)

                ->where('actif', true)

                ->first()

            : null;



        if ($template) {

            $variables = array_merge([

                'titre' => $titre,

                'message' => $message,

                'url' => $url,

            ], $variables);

            $message = $template->appliquer($variables);

            if ($template->sujet) {

                $titre = $template->appliquer(array_merge($variables, ['sujet' => $template->sujet]));

            }

        }



        $users = $this->normaliserDestinataires($destinataires);

        if ($users->isEmpty()) {

            return 0;

        }



        $lignes = $users->map(fn (User $u) => [

            'user' => $u,

            'canaux' => $this->preferences->canauxEffectifs($u, $typeCode, $criticite, $canaux),

        ])->filter(fn (array $row) => $row['canaux'] !== []);



        if ($lignes->isEmpty()) {

            return 0;

        }



        $notification = new InstitutionInAppNotification(

            $typeCode,

            $module,

            $criticite,

            $titre,

            $message,

            $url,

            $meta,

        );



        $count = 0;



        DB::transaction(function () use ($lignes, $notification, $type, $typeCode, $module, $criticite, $titre, $message, $url, $variables, $meta, $emetteur, &$count) {

            $usersDatabase = $lignes->filter(fn ($r) => in_array('database', $r['canaux'], true))->pluck('user');

            if ($usersDatabase->isNotEmpty()) {

                Notification::send($usersDatabase, $notification);

            }



            foreach ($lignes as $row) {

                $user = $row['user'];

                $userCanaux = $row['canaux'];

                $laravelId = in_array('database', $userCanaux, true)

                    ? $user->notifications()->latest()->value('id')

                    : null;



                $log = NotificationLog::create([

                    'notification_type_id' => $type?->id,

                    'type_code' => $typeCode,

                    'module' => $module,

                    'criticite' => $criticite,

                    'canal' => implode(',', $userCanaux),

                    'destinataire_type' => $user->getMorphClass(),

                    'destinataire_id' => $user->getKey(),

                    'emetteur_id' => $emetteur?->id,

                    'titre' => $titre,

                    'message' => $message,

                    'url' => $url,

                    'statut' => 'envoye',

                    'metadata' => $meta ?: null,

                    'laravel_notification_id' => $laravelId,

                    'envoye_le' => now(),

                ]);



                if ($laravelId) {

                    DB::table('notifications')->where('id', $laravelId)->update([

                        'type_code' => $typeCode,

                        'module' => $module,

                        'criticite' => $criticite,

                    ]);

                }



                if (in_array('email', $userCanaux, true) && $user->email) {

                    $this->emails->envoyer(

                        $user->email,

                        $typeCode,

                        $titre,

                        $message,

                        $variables,

                        $url,

                        $user,

                        module: $module,

                        meta: $meta,

                        notificationLogId: $log->id,

                        forceSync: app()->runningUnitTests(),

                    );

                    $this->audit->journaliser('envoi_email', $typeCode, $module, 'email', $emetteur, $user, $log->id, payload: $meta);

                }



                if (in_array('sms', $userCanaux, true) && $user->telephone) {

                    $this->sms->envoyer(

                        $user->telephone,

                        $typeCode,

                        $message,

                        array_merge($variables, ['titre' => $titre]),

                        $user,

                        $module,

                        $log->id,

                        $meta,

                    );

                    $this->audit->journaliser('envoi_sms', $typeCode, $module, 'sms', $emetteur, $user, $log->id, payload: $meta);

                }



                $this->audit->journaliser('envoi', $typeCode, $module, 'database', $emetteur, $user, $log->id, payload: $meta);



                $count++;

            }

        });



        return $count;

    }



    /** @param  Collection<int, User>|User|iterable<User>  $destinataires */

    public function envoyerSiAutorise(

        Collection|User|iterable $destinataires,

        string $typeCode,

        string $titre,

        string $message,

        ?string $url = null,

        ?string $module = null,

        string $criticite = NotificationCriticite::INFORMATION,

        array $meta = [],

        array $canaux = ['database'],

    ): int {

        try {

            return $this->envoyer(

                $destinataires,

                $typeCode,

                $titre,

                $message,

                $url,

                $module,

                $criticite,

                meta: $meta,

                emetteur: auth()->user(),

                canaux: $canaux,

            );

        } catch (RuntimeException) {

            return 0;

        }

    }



    private function autoriseEnvoi(?User $emetteur): bool

    {

        if ($emetteur === null) {

            return true;

        }



        return $emetteur->can('notifications.send') || $emetteur->can('notifications.admin');

    }



    /** @return Collection<int, User> */

    private function normaliserDestinataires(Collection|User|iterable $destinataires): Collection

    {

        if ($destinataires instanceof User) {

            return collect([$destinataires]);

        }



        return collect($destinataires)

            ->filter(fn ($u) => $u instanceof User && $u->actif)

            ->unique('id')

            ->values();

    }

}

