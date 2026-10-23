<?php



namespace App\Console\Commands;



use App\Services\Budget\StructureBudgetRecetteService;

use Illuminate\Console\Command;



/** Recette automatisée MOA — module Structure du Budget, exercice 2026 (Lot 7). */

class StructureBudgetRecette2026Command extends Command

{

    protected $signature = 'structure-budget:recette-2026 {--exercice= : ID exercice (défaut : année 2026)}';



    protected $description = 'Exécute les contrôles de recette MOA Structure du Budget (Budget 2026)';



    public function handle(StructureBudgetRecetteService $recette): int

    {

        $exerciceId = $this->option('exercice') ? (int) $this->option('exercice') : null;

        $resultat = $recette->executer($exerciceId);



        $this->info(sprintf(

            'Recette Structure du Budget — exercice %s — %d/%d contrôles OK',

            $resultat['annee'] ?? '?',

            $resultat['score'],

            $resultat['total']

        ));

        $this->newLine();



        foreach ($resultat['controles'] as $c) {

            $icon = $c['ok'] ? '<fg=green>✓</>' : '<fg=red>✗</>';

            $this->line(" {$icon} [{$c['code']}] {$c['libelle']}");

            $this->line("     {$c['detail']}");

        }



        if (! $resultat['ok']) {

            $this->newLine();

            $this->error('Recette NON conforme — corriger les écarts avant signature MOA.');



            return self::FAILURE;

        }



        $this->newLine();

        $this->info('Recette CONFORME — prête pour signature PV-REC-SB-2026-01.');



        return self::SUCCESS;

    }

}


