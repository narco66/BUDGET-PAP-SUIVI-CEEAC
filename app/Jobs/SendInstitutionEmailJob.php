<?php



namespace App\Jobs;



use App\Mail\InstitutionEmail;

use App\Models\EmailLog;

use App\Support\QueueNames;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Queue\Queueable;

use Illuminate\Support\Facades\Mail;

use Throwable;



/** Envoi asynchrone d'un courriel institutionnel avec journalisation (N-P2). */

class SendInstitutionEmailJob implements ShouldQueue

{

    use Queueable;



    public int $tries = 3;



    public function __construct(public readonly int $emailLogId)
    {
        $this->onQueue(QueueNames::notifications());
    }



    public function handle(): void

    {

        $log = EmailLog::find($this->emailLogId);

        if (! $log || $log->statut === 'envoye') {

            return;

        }



        $log->increment('tentatives');



        try {

            Mail::to($log->destinataire_email)->send(new InstitutionEmail(

                $log->sujet,

                $log->corps_html ?? '',

                strip_tags($log->corps_html ?? ''),

                $log->metadata['url'] ?? null,

            ));



            $log->update([

                'statut' => 'envoye',

                'envoye_le' => now(),

                'erreur' => null,

            ]);

        } catch (Throwable $e) {

            $log->update([

                'statut' => $log->tentatives >= $log->max_tentatives ? 'echec' : 'en_attente',

                'erreur' => $e->getMessage(),

            ]);



            throw $e;

        }

    }

}

