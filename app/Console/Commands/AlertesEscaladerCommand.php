<?php



namespace App\Console\Commands;



use App\Services\Alertes\AlerteEscaladeService;

use Illuminate\Console\Command;



/** Escalade automatique des alertes critiques ouvertes (N-P3). */

class AlertesEscaladerCommand extends Command

{

    protected $signature = 'alertes:escalader';



    protected $description = 'Escalade les alertes critiques ouvertes selon les niveaux institutionnels';



    public function handle(AlerteEscaladeService $service): int

    {

        $count = $service->traiterAutomatique();

        $this->info("{$count} escalade(s) effectuée(s).");



        return self::SUCCESS;

    }

}

