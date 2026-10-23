<?php

namespace App\Console\Commands;

use App\Services\Alertes\AlerteBudgetDetector;
use Illuminate\Console\Command;

/** Détection planifiée des alertes budgétaires (N-P1). */
class AlertesDetecterCommand extends Command
{
    protected $signature = 'alertes:detecter-budget {--exercice= : ID exercice (optionnel)}';

    protected $description = 'Détecte les alertes budgétaires et financières';

    public function handle(AlerteBudgetDetector $detector): int
    {
        $exerciceId = $this->option('exercice') ? (int) $this->option('exercice') : null;
        $count = $detector->detecter($exerciceId);
        $this->info("{$count} alerte(s) budgétaire(s) enregistrée(s).");

        return self::SUCCESS;
    }
}
