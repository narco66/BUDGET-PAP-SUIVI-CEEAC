<?php

namespace App\Console\Commands;

use App\Jobs\GenererRapportJob;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Reporting\RapportPeriodeService;
use App\Services\Reporting\RapportRenderer;
use App\Services\Reporting\ReportingAsyncPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Base CLI — génération d'un rapport périodique institutionnel (P1). */
abstract class GenererRapportPeriodiqueCommand extends Command
{
    abstract protected function typeRapport(): string;

    abstract protected function periodeParDefaut(int $annee): string;

    public function handle(RapportRenderer $renderer, RapportPeriodeService $periodes): int
    {
        $exercice = $this->resoudreExercice();
        if (! $exercice) {
            $this->error('Exercice introuvable.');

            return self::FAILURE;
        }

        $user = User::role('administrateur')->first() ?? User::first();
        if (! $user) {
            $this->error('Aucun utilisateur disponible pour la génération.');

            return self::FAILURE;
        }

        $format = $this->option('format') ?: 'pdf';
        $periodeSaisie = $this->input->hasOption('periode') && $this->option('periode')
            ? $this->option('periode')
            : $this->periodeParDefaut($exercice->annee);

        try {
            $periode = $periodes->resoudre($exercice, $periodeSaisie);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $type = $this->typeRapport();

        if (ReportingAsyncPolicy::doitAsync($type, $format) && config('queue.default') !== 'sync') {
            GenererRapportJob::dispatch($type, $exercice->id, $format, $periode, $user->id);
            $this->info("Job de génération « {$type} » ({$format}) dispatché pour l'exercice {$exercice->annee}.");

            return self::SUCCESS;
        }

        $resultat = $renderer->generer(
            $type,
            $exercice,
            $format,
            $periode,
            $user,
        );

        $rapport = $resultat['rapport'];
        $this->info("Rapport {$rapport->reference} v{$rapport->version} généré ({$format}).");
        $this->line('Période : '.$rapport->periode);
        $this->line('Chemin   : '.Storage::disk('local')->path($rapport->chemin));

        return self::SUCCESS;
    }

    private function resoudreExercice(): ?Exercice
    {
        $opt = $this->option('exercice');
        if ($opt) {
            return is_numeric($opt)
                ? (Exercice::find((int) $opt) ?? Exercice::firstWhere('annee', (int) $opt))
                : null;
        }

        return Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->first();
    }
}
