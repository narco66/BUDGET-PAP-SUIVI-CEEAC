<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Rbm\RbmAuditService;
use Illuminate\Console\Command;

/** Audit d'intégrité du référentiel institutionnel RBM/GAR (phase E7). */
class RbmAuditCommand extends Command
{
    protected $signature = 'rbm:audit {--exercice= : ID ou année} {--json : Sortie JSON}';

    protected $description = 'Audit RBM/GAR : chaîne hiérarchique, IOV, PAP, blocages archivage/suppression';

    public function handle(RbmAuditService $audit): int
    {
        $exerciceId = null;
        if ($this->option('exercice')) {
            $opt = $this->option('exercice');
            $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
        }
        if (! $exerciceId) {
            $exerciceId = Exercice::firstWhere('statut', 'ouvert')?->id
                ?? Exercice::latest('annee')->value('id');
        }

        abort_unless($exerciceId, 1, 'Aucun exercice trouvé.');

        $rapport = $audit->executer((int) $exerciceId);

        if ($this->option('json')) {
            $this->line(json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $rapport['anomalies_critiques'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Audit RBM/GAR — exercice ID '.$exerciceId);
        if ($rapport['synthese']) {
            foreach ($rapport['synthese'] as $k => $v) {
                $this->line(ucfirst(str_replace('_', ' ', $k)).' : '.$v);
            }
        }
        $this->newLine();

        foreach ($rapport['anomalies'] as $a) {
            $line = "[{$a['niveau']}] {$a['code']} — {$a['message']}";
            match ($a['niveau']) {
                'critique' => $this->error($line),
                'warning' => $this->warn($line),
                default => $this->line($line),
            };
        }

        $this->newLine();
        $this->info('Total anomalies : '.$rapport['anomalies_total'].' (critiques : '.$rapport['anomalies_critiques'].')');

        return $rapport['anomalies_critiques'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
