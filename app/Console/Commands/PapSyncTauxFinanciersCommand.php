<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Pap\PapPerformanceService;
use Illuminate\Console\Command;

/** Recalcule et persiste les taux financiers PAP depuis l'exécution budgétaire réelle. */
class PapSyncTauxFinanciersCommand extends Command
{
    protected $signature = 'pap:sync-taux-financiers {--exercice= : ID ou année de l\'exercice}';

    protected $description = 'Synchronise activite.taux_realisation_financier depuis montant_paye des lignes budgétaires';

    public function handle(PapPerformanceService $performance): int
    {
        $opt = $this->option('exercice');
        if (! $opt) {
            $this->error('Option --exercice requise (ID ou année).');

            return self::FAILURE;
        }

        $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
        if (! $exerciceId) {
            $this->error("Exercice introuvable : {$opt}");

            return self::FAILURE;
        }

        $count = $performance->synchroniserTauxFinanciersExercice($exerciceId);
        $exec = $performance->executionPap($exerciceId);

        $this->info("Taux financiers synchronisés : {$count} activité(s) mise(s) à jour.");
        $this->line("Taux financier global (payé/prévu) : {$exec['taux_financier']} %");
        $this->line("Montant payé : ".number_format($exec['montant_paye'], 0, ',', ' ')." XAF / ".number_format($exec['montant_prevu'], 0, ',', ' ')." XAF");

        return self::SUCCESS;
    }
}
