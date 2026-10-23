<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Budget\CompteurMouvementAuditService;
use Illuminate\Console\Command;

/** Audit sémantique des mouvements de compteurs budgétaires (chaîne de la dépense). */
class BudgetAuditCompteursCommand extends Command
{
    protected $signature = 'budget:audit-compteurs {--exercice= : ID ou année de l\'exercice} {--json : Sortie JSON}';

    protected $description = 'Reconstitue les compteurs depuis engagements/liquidations/ordonnancements/paiements et détecte les écarts';

    public function handle(CompteurMouvementAuditService $audit): int
    {
        $exerciceId = null;
        if ($this->option('exercice')) {
            $opt = $this->option('exercice');
            $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
        }

        if (! $exerciceId) {
            $this->error('Option --exercice requise (ID ou année).');

            return self::FAILURE;
        }

        $rapport = $audit->analyser($exerciceId);

        if ($this->option('json')) {
            $this->line(json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ($rapport['total_anomalies'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Audit sémantique des mouvements de compteurs');
        $this->line("Exercice ID : {$exerciceId}");
        $this->table(
            ['Indicateur', 'Valeur'],
            collect($rapport['synthese'])->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        if ($rapport['anomalies_semantiques']) {
            $this->newLine();
            $this->comment('Anomalies sémantiques :');
            foreach ($rapport['anomalies_semantiques'] as $a) {
                $this->warn("  · [{$a['type']}] {$a['ref']}");
            }
        }

        if ($rapport['ecarts_compteurs']) {
            $this->newLine();
            $this->comment('Écarts compteurs / lignes :');
            foreach ($rapport['ecarts_compteurs'] as $e) {
                $this->warn("  · {$e['code']} {$e['colonne']} : attendu {$e['attendu']}, réel {$e['reel']}");
            }
        }

        $total = (int) ($rapport['total_anomalies'] ?? 0);
        $this->newLine();
        $this->info("Total anomalies : {$total}");

        return $total > 0 ? self::FAILURE : self::SUCCESS;
    }
}
