<?php

namespace App\Console\Commands;

use App\Models\ImportLot;
use App\Models\User;
use App\Services\Import\ImportExecutionService;
use Illuminate\Console\Command;

/**
 * Reprise contrôlée d'un lot resté « en cours » (job perdu, arrêt du serveur). L'exécution étant
 * transactionnelle, un lot interrompu n'a rien écrit : il est rejoué entièrement, contrôle de dérive compris.
 */
class ImportExecuterCommand extends Command
{
    protected $signature = 'import:executer {reference : Référence du lot (IMP-AAAA-NNNNNN)}';

    protected $description = 'Rejoue l\'exécution d\'un lot d\'import resté en cours (reprise après interruption).';

    public function handle(ImportExecutionService $execution): int
    {
        $lot = ImportLot::query()->where('reference', $this->argument('reference'))->first();
        if (! $lot) {
            $this->error('Lot introuvable.');

            return self::FAILURE;
        }
        if ($lot->statut !== 'en_cours') {
            $this->error("Lot au statut « {$lot->libelleStatut()} » : seule une exécution interrompue peut être reprise.");

            return self::FAILURE;
        }
        $user = User::query()->find($lot->execute_par);
        if (! $user) {
            $this->error('Exécutant introuvable.');

            return self::FAILURE;
        }
        auth()->setUser($user);
        $lot = $execution->executer($lot, $user);
        $this->info("Lot {$lot->reference} : {$lot->libelleStatut()}.".($lot->motif ? " {$lot->motif}" : ''));

        return $lot->statut === 'termine' ? self::SUCCESS : self::FAILURE;
    }
}
