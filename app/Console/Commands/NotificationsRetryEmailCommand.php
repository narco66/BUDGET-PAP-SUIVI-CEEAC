<?php



namespace App\Console\Commands;



use App\Services\Notifications\EmailNotificationService;

use Illuminate\Console\Command;



/** Relance les envois e-mail en échec (N-P2). */

class NotificationsRetryEmailCommand extends Command

{

    protected $signature = 'notifications:retry-email {--limit=50 : Nombre max de relances}';



    protected $description = 'Relance les courriels institutionnels en échec';



    public function handle(EmailNotificationService $service): int

    {

        $count = $service->retenterEchecs((int) $this->option('limit'));

        $this->info("{$count} courriel(s) remis en file d'attente.");



        return self::SUCCESS;

    }

}

