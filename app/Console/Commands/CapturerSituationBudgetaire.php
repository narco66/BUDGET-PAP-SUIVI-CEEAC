<?php

namespace App\Console\Commands;

use App\Services\Budget\SituationBudgetaireService;
use Illuminate\Console\Command;

/** Phase P1 — capture d'une situation budgétaire consolidée. */
class CapturerSituationBudgetaire extends Command
{
    protected $signature = 'budget:situation-capture {--exercice= : ID ou année de l\'exercice}';

    protected $description = 'Capture la situation budgétaire consolidée (snapshot journalier)';

    public function handle(SituationBudgetaireService $service): int
    {
        $exerciceId = $service->resoudreExerciceId($this->option('exercice'));
        if (! $exerciceId) {
            $this->error('Exercice introuvable.');

            return self::FAILURE;
        }

        $situation = $service->capturer($exerciceId);
        $this->info("Situation #{$situation->id} capturée pour l'exercice {$exerciceId} ({$situation->date_situation->format('Y-m-d')}).");
        $this->line('Reste à engager : '.number_format((float) $situation->reste_a_engager, 0, ',', ' ').' FCFA');
        $this->line('Reste à payer : '.number_format((float) $situation->reste_a_payer, 0, ',', ' ').' FCFA');

        return self::SUCCESS;
    }
}
