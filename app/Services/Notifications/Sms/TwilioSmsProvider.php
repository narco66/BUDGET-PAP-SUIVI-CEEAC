<?php



namespace App\Services\Notifications\Sms;



use App\Contracts\Notifications\SmsProviderInterface;

use Illuminate\Support\Facades\Http;

use RuntimeException;



/** Provider Twilio (production) — N-P3. */

class TwilioSmsProvider implements SmsProviderInterface

{

    public function nom(): string

    {

        return 'twilio';

    }



    public function envoyer(string $telephone, string $message): void

    {

        $sid = config('notifications.sms.twilio.sid');

        $token = config('notifications.sms.twilio.token');

        $from = config('notifications.sms.twilio.from');



        if (! $sid || ! $token || ! $from) {

            throw new RuntimeException('Configuration Twilio incomplète.');

        }



        $response = Http::withBasicAuth($sid, $token)

            ->asForm()

            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [

                'From' => $from,

                'To' => $telephone,

                'Body' => mb_substr($message, 0, 320),

            ]);



        if (! $response->successful()) {

            throw new RuntimeException('Twilio : '.$response->body());

        }

    }

}

