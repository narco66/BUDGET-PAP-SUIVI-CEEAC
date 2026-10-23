<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Reporting\RapportRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Génération CLI de l'état financier IPSAS (P3). */
class GenererRapportIpsas extends Command
{
    protected $signature = 'reporting:generer-ipsas
                            {--exercice= : ID ou année de l\'exercice}
                            {--format=pdf : pdf|excel|word|csv}';

    protected $description = 'Génère l\'état financier IPSAS (données préparatoires)';

    public function handle(RapportRenderer $renderer): int
    {
        $exercice = $this->resoudreExercice();
        if (! $exercice) {
            $this->error('Exercice introuvable.');

            return self::FAILURE;
        }

        $user = User::role('administrateur')->first() ?? User::first();
        if (! $user) {
            $this->error('Aucun utilisateur disponible.');

            return self::FAILURE;
        }

        $format = $this->option('format') ?: 'pdf';
        $resultat = $renderer->generer(
            'ipsas_etat_financier',
            $exercice,
            $format,
            ['periode' => (string) $exercice->annee],
            $user,
        );

        $rapport = $resultat['rapport'];
        $this->info("État IPSAS {$rapport->reference} v{$rapport->version} généré ({$format}).");
        $this->line('Chemin : '.Storage::disk('local')->path($rapport->chemin));

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
