<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Budget\BudgetAuditService;
use Illuminate\Console\Command;

/** Lance l'audit d'intégrité nomenclature budgétaire (TB-PAPA-CEEAC). */
class BudgetAuditCommand extends Command
{
    protected $signature = 'budget:audit {--exercice= : ID ou année de l\'exercice} {--json : Sortie JSON}';

    protected $description = 'Audit d\'intégrité nomenclature, crédits, RBM, référentiel financier, PTF et mouvements de compteurs';

    public function handle(BudgetAuditService $audit): int
    {
        $exerciceId = null;
        if ($this->option('exercice')) {
            $opt = $this->option('exercice');
            $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
        }

        $rapport = $audit->executer($exerciceId);

        if ($this->option('json')) {
            $this->line(json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ($rapport['anomalies_total'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Audit nomenclature budgétaire TB-PAPA-CEEAC');
        $this->line('Exercice : '.($exerciceId ? "ID {$exerciceId}" : 'tous'));
        $this->newLine();

        foreach ($rapport as $section => $data) {
            if (in_array($section, ['exercice_id', 'genere_le', 'anomalies_total'], true)) {
                continue;
            }
            $this->comment(strtoupper(str_replace('_', ' ', $section)));
            foreach ((array) $data as $k => $v) {
                if ($k === 'anomalies' && is_array($v)) {
                    $this->line("  {$k} : ".count($v));
                    foreach (array_slice($v, 0, 10) as $a) {
                        $detail = is_array($a) ? ($a['code'].' — '.$a['type']) : (string) $a;
                        $this->warn("    · {$detail}");
                    }
                    if (count($v) > 10) {
                        $this->line('    … '.(count($v) - 10).' autre(s)');
                    }
                } else {
                    $this->line("  {$k} : ".(is_array($v) ? json_encode($v) : $v));
                }
            }
            $this->newLine();
        }

        $total = (int) ($rapport['anomalies_total'] ?? 0);
        $this->info("Total anomalies : {$total}");

        return $total > 0 ? self::FAILURE : self::SUCCESS;
    }
}
