<?php



namespace App\Services\Notifications;



use App\Contracts\Notifications\SmsProviderInterface;

use App\Models\NotificationType;

use App\Models\SmsLog;

use App\Models\SmsTemplate;

use App\Models\User;

use App\Services\Notifications\Sms\LogSmsProvider;

use App\Services\Notifications\Sms\TwilioSmsProvider;



/** Moteur SMS institutionnel : templates, provider, journal (N-P3). */

class SmsNotificationService

{

    /** @param  array<string, scalar|null>  $variables */

    public function envoyer(

        string $telephone,

        string $typeCode,

        string $message,

        array $variables = [],

        ?User $destinataireUser = null,

        ?string $module = null,

        ?int $notificationLogId = null,

        array $meta = [],

    ): ?SmsLog {

        if (! config('notifications.sms.enabled', true) || trim($telephone) === '') {

            return null;

        }



        $type = NotificationType::where('code', $typeCode)->where('actif', true)->first();

        $module ??= $type?->module ?? 'systeme';



        $template = SmsTemplate::where('code', $typeCode)->where('actif', true)->first();

        $variables = array_merge(['message' => $message, 'titre' => $message], $variables);

        $texte = $template ? $template->appliquer($variables) : mb_substr($message, 0, 320);



        $provider = $this->provider();

        $log = SmsLog::create([

            'sms_template_id' => $template?->id,

            'notification_log_id' => $notificationLogId,

            'type_code' => $typeCode,

            'module' => $module,

            'destinataire_telephone' => $telephone,

            'destinataire_type' => $destinataireUser?->getMorphClass(),

            'destinataire_id' => $destinataireUser?->getKey(),

            'message' => $texte,

            'statut' => 'en_attente',

            'provider' => $provider->nom(),

            'max_tentatives' => config('notifications.sms.max_tentatives', 3),

            'metadata' => $meta ?: null,

        ]);



        try {

            $log->increment('tentatives');

            $provider->envoyer($telephone, $texte);

            $log->update(['statut' => 'envoye', 'envoye_le' => now(), 'erreur' => null]);

        } catch (\Throwable $e) {

            $log->update([

                'statut' => $log->tentatives >= $log->max_tentatives ? 'echec' : 'en_attente',

                'erreur' => $e->getMessage(),

            ]);

        }



        return $log->fresh();

    }



    private function provider(): SmsProviderInterface

    {

        return match (config('notifications.sms.provider', 'log')) {

            'twilio' => app(TwilioSmsProvider::class),

            default => app(LogSmsProvider::class),

        };

    }

}

