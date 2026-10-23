<?php



namespace App\Services\Notifications\Sms;



use App\Contracts\Notifications\SmsProviderInterface;

use Illuminate\Support\Facades\Log;



/** Provider SMS de développement / tests — journalise sans envoi réseau. */

class LogSmsProvider implements SmsProviderInterface

{

    public function nom(): string

    {

        return 'log';

    }



    public function envoyer(string $telephone, string $message): void

    {

        Log::channel('single')->info('[SMS CEEAC]', [

            'to' => $telephone,

            'message' => $message,

        ]);

    }

}

