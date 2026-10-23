<?php

namespace App\Console\Commands;

use App\Services\Interop\ApiMonitoringService;
use Illuminate\Console\Command;

/** Agrège les logs API quotidiens en métriques (I-P3). */
class AgregerApiMetriquesCommand extends Command
{
    protected $signature = 'api:agreger-metriques {--jour=}';

    protected $description = 'Agrège api_logs vers api_metrics pour le monitoring';

    public function handle(ApiMonitoringService $monitoring): int
    {
        $jour = $this->option('jour')
            ? \Illuminate\Support\Carbon::parse($this->option('jour'))
            : null;

        $nb = $monitoring->agregerJour($jour);
        $this->info("{$nb} ligne(s) de métriques agrégée(s).");

        return self::SUCCESS;
    }
}
