<?php



namespace App\Services\Notifications;



use App\Jobs\SendInstitutionEmailJob;

use App\Models\EmailLog;

use App\Models\EmailTemplate;

use App\Models\NotificationType;

use App\Models\User;

use Illuminate\Support\Facades\Mail;



/** Moteur e-mail institutionnel : templates MOA, file d'attente, retry (N-P2). */

class EmailNotificationService

{

    /**

     * @param  array<string, scalar|null>  $variables

     * @param  array<string, mixed>  $meta

     */

    public function envoyer(

        string $destinataireEmail,

        string $typeCode,

        string $sujet,

        string $message,

        array $variables = [],

        ?string $url = null,

        ?User $destinataireUser = null,

        ?string $destinataireNom = null,

        ?string $module = null,

        array $meta = [],

        ?int $notificationLogId = null,

        bool $forceSync = false,

    ): ?EmailLog {

        if (! config('notifications.email.enabled', true) || trim($destinataireEmail) === '') {

            return null;

        }



        $type = NotificationType::where('code', $typeCode)->where('actif', true)->first();

        $module ??= $type?->module ?? 'systeme';



        $template = EmailTemplate::where('code', $typeCode)->where('actif', true)->first();

        $variables = array_merge([

            'titre' => $sujet,

            'message' => $message,

            'url' => $url ?? '',

            'sujet' => $sujet,

        ], $variables);



        if ($template) {

            $sujet = $template->appliquerSujet($variables);

            $html = $template->appliquerCorpsSecurise($variables, 'html');
        } else {

            $html = nl2br(e($message));

        }



        $log = EmailLog::create([

            'email_template_id' => $template?->id,

            'notification_log_id' => $notificationLogId,

            'notification_type_id' => $type?->id,

            'type_code' => $typeCode,

            'module' => $module,

            'destinataire_email' => $destinataireEmail,

            'destinataire_nom' => $destinataireNom ?? $destinataireUser?->name,

            'destinataire_type' => $destinataireUser?->getMorphClass(),

            'destinataire_id' => $destinataireUser?->getKey(),

            'sujet' => $sujet,

            'corps_html' => $html,

            'statut' => 'en_attente',

            'max_tentatives' => config('notifications.email.max_tentatives', 3),

            'metadata' => array_merge($meta, array_filter(['url' => $url])),

        ]);



        if ($forceSync || ! config('notifications.email.queue', true)) {

            $this->envoyerSync($log);

        } else {

            SendInstitutionEmailJob::dispatch($log->id);

        }



        return $log;

    }



    /** @return int nombre d'e-mails relancés */

    public function retenterEchecs(?int $limite = 50): int

    {

        $count = 0;



        EmailLog::query()

            ->where('statut', 'echec')

            ->whereColumn('tentatives', '<', 'max_tentatives')

            ->orderBy('id')

            ->limit($limite)

            ->each(function (EmailLog $log) use (&$count) {

                $log->update(['statut' => 'en_attente', 'erreur' => null]);

                SendInstitutionEmailJob::dispatch($log->id);

                $count++;

            });



        return $count;

    }



    private function envoyerSync(EmailLog $log): void

    {

        $log->increment('tentatives');



        try {

            Mail::to($log->destinataire_email)->send(new \App\Mail\InstitutionEmail(

                $log->sujet,

                $log->corps_html ?? '',

                strip_tags($log->corps_html ?? ''),

                $log->metadata['url'] ?? null,

            ));



            $log->update(['statut' => 'envoye', 'envoye_le' => now(), 'erreur' => null]);

        } catch (\Throwable $e) {

            $log->update([

                'statut' => $log->tentatives >= $log->max_tentatives ? 'echec' : 'en_attente',

                'erreur' => $e->getMessage(),

            ]);

        }

    }

}

