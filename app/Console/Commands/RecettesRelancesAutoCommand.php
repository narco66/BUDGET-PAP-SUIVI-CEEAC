<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Recettes\RelanceService;
use Illuminate\Console\Command;

/** Génère les relances automatiques de recouvrement pour les appels de fonds en retard. */
class RecettesRelancesAutoCommand extends Command
{
    protected $signature = 'recettes:relances-auto {--exercice= : ID ou année de l\'exercice (facultatif)}';

    protected $description = 'Crée les relances automatiques pour les appels de fonds échus et non soldés';

    public function handle(RelanceService $service): int
    {
        $opt = $this->option('exercice');
        $exerciceId = null;

        if ($opt) {
            $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
            if (! $exerciceId) {
                $this->error("Exercice introuvable : {$opt}");

                return self::FAILURE;
            }
        }

        $count = $service->genererAutomatiques($exerciceId);

        $this->info("Relances automatiques générées : {$count}.");

        return self::SUCCESS;
    }
}
