<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Rbm\RbmDuplicationService;
use Illuminate\Console\Command;

/** Reporte le référentiel RBM d'un exercice vers un autre (phase E8). */
class RbmReporterExerciceCommand extends Command
{
    protected $signature = 'rbm:reporter-exercice
                            {--source= : ID ou année source}
                            {--cible= : ID ou année cible}
                            {--force : Remplace le référentiel existant sur la cible}
                            {--dry-run : Simule sans écrire}';

    protected $description = 'Duplique la chaîne RBM/GAR (sans mesures ni liens budget) vers un autre exercice';

    public function handle(RbmDuplicationService $service): int
    {
        $sourceId = $this->resoudreExercice($this->option('source'));
        $cibleId = $this->resoudreExercice($this->option('cible'));

        abort_unless($sourceId && $cibleId, 1, 'Options --source et --cible requises (ID ou année).');

        $source = Exercice::findOrFail($sourceId);
        $cible = Exercice::findOrFail($cibleId);

        if ($this->option('dry-run')) {
            $this->info("Simulation : report {$source->annee} → {$cible->annee}");
            $this->line('Piliers source : '.$source->piliers()->count());

            return self::SUCCESS;
        }

        $user = User::role('administrateur')->first() ?? User::first();
        abort_unless($user, 1, 'Aucun utilisateur pour la traçabilité.');

        try {
            $stats = $service->dupliquerReferentiel($source, $cible, $user, (bool) $this->option('force'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($stats as $k => $v) {
            $this->line("{$k} : {$v}");
        }

        $this->info('Report terminé.');

        return self::SUCCESS;
    }

    private function resoudreExercice(?string $opt): ?int
    {
        if (! $opt) {
            return null;
        }

        return Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
    }
}
