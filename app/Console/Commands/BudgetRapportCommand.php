<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Budget\BudgetReportService;
use Illuminate\Console\Command;

/** Phase B5 — rapport de synthèse du cycle budgétaire institutionnel. */
class BudgetRapportCommand extends Command
{
    protected $signature = 'budget:rapport {--exercice= : ID ou année de l\'exercice} {--json : Sortie JSON}';

    protected $description = 'Rapport de synthèse du cycle budgétaire (enveloppe, préparation, consolidation, révisions)';

    public function handle(BudgetReportService $rapport): int
    {
        $exerciceId = $this->resoudreExercice($this->option('exercice'));
        $data = $rapport->synthese($exerciceId);

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $ex = $data['exercice']['annee'] ?? '?';
        $this->info("Rapport budgétaire institutionnel — exercice {$ex}");
        $this->line('Enveloppe : '.($data['enveloppe']['reference'] ?? 'absente').' ('.($data['enveloppe']['statut'] ?? '—').')');
        $this->line('Crédits ouverts : '.number_format($data['montants']['ouvert'] ?? 0, 0, ',', ' ').' FCFA');
        $this->line('Anomalies audit : '.($data['audit']['anomalies_total'] ?? 0));
        $this->line('Conformité cycle : '.($data['conformite']['score_pct'] ?? 0).'%');
        $this->line('Alertes cycle : '.($data['audit']['cycle_institutionnel']['total_alertes'] ?? 0));

        return self::SUCCESS;
    }

    private function resoudreExercice(?string $opt): ?int
    {
        if (! $opt) {
            return \App\Services\Budget\ExerciceService::courant()?->id;
        }

        if (is_numeric($opt)) {
            $ex = Exercice::find((int) $opt) ?? Exercice::firstWhere('annee', (int) $opt);

            return $ex?->id;
        }

        return null;
    }
}
