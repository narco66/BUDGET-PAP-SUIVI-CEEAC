<?php

namespace App\Console\Commands;

use App\Services\Suivi\SuiviEvaluationService;
use Illuminate\Console\Command;

/** Phase P1 — capture batch des évaluations périodiques S&E. */
class CapturerEvaluationsPeriodiques extends Command
{
    protected $signature = 'suivi-evaluation:capture {--exercice= : ID ou année de l\'exercice}';

    protected $description = 'Génère les snapshots d\'évaluation périodique (annuelle + campagnes clôturées)';

    public function handle(SuiviEvaluationService $service): int
    {
        $exerciceId = $service->resoudreExerciceId($this->option('exercice'));
        if (! $exerciceId) {
            $this->error('Exercice introuvable.');

            return self::FAILURE;
        }

        $n = $service->capturerExercice($exerciceId);
        $this->info("{$n} évaluation(s) périodique(s) capturée(s) pour l'exercice {$exerciceId}.");

        return self::SUCCESS;
    }
}
