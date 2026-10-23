<?php

namespace App\Console\Commands;

use App\Services\Rbm\IndicateurPerformanceService;
use Illuminate\Console\Command;

/** Phase P2 — capture des performances IOV persistées. */
class CapturerPerformancesIndicateurs extends Command
{
    protected $signature = 'indicateurs:capture-performances {--exercice= : ID ou année}';

    protected $description = 'Persiste les performances IOV (mesures validées) dans performances_indicateurs';

    public function handle(IndicateurPerformanceService $service): int
    {
        $opt = $this->option('exercice');
        $exerciceId = null;

        if ($opt) {
            $ex = is_numeric($opt)
                ? \App\Models\Exercice::find((int) $opt) ?? \App\Models\Exercice::firstWhere('annee', (int) $opt)
                : null;
            $exerciceId = $ex?->id;
        } else {
            $exerciceId = \App\Services\Budget\ExerciceService::courant()?->id;
        }

        if (! $exerciceId) {
            $this->error('Exercice introuvable.');

            return self::FAILURE;
        }

        $n = $service->capturerExercice($exerciceId);
        $this->info("$n performance(s) IOV capturée(s) pour l'exercice #$exerciceId.");

        return self::SUCCESS;
    }
}
