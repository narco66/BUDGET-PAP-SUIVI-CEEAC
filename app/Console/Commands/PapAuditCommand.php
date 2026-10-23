<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Pap\PapAuditService;
use Illuminate\Console\Command;

/** Audit d'intégrité et complétude du PAP (TB-PAPA-CEEAC). */
class PapAuditCommand extends Command
{
    protected $signature = 'pap:audit {--exercice= : ID ou année} {--json : Sortie JSON}';

    protected $description = 'Audit du Plan Annuel de Performance (chaîne RBM, indicateurs, budget, consolidation)';

    public function handle(PapAuditService $audit): int
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

        $rapport = $audit->executer($exerciceId);

        if ($this->option('json')) {
            $this->line(json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $rapport['anomalies_critiques'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Audit PAP — exercice ID '.$exerciceId);
        $this->line('Activités : '.$rapport['synthese']['activites']);
        $this->line('Taux physique moyen : '.$rapport['synthese']['taux_physique'].' %');
        $this->line('Taux financier réel : '.$rapport['synthese']['taux_financier'].' %');
        $this->newLine();

        foreach ($rapport['anomalies'] as $a) {
            $line = "[{$a['niveau']}] {$a['code']} — {$a['message']}";
            match ($a['niveau']) {
                'critique' => $this->error($line),
                'warning' => $this->warn($line),
                default => $this->line($line),
            };
        }

        return $rapport['anomalies_critiques'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
