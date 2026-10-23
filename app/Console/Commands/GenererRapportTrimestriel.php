<?php

namespace App\Console\Commands;

use App\Services\Reporting\RapportPeriodeService;

class GenererRapportTrimestriel extends GenererRapportPeriodiqueCommand
{
    protected $signature = 'reporting:generer-trimestre
                            {--exercice= : ID ou année de l\'exercice}
                            {--periode= : Période ex. 2026-T1 (défaut : trimestre courant)}
                            {--format=pdf : pdf, excel, word ou csv}';

    protected $description = 'Génère le rapport trimestriel institutionnel (PDF/Excel/Word/CSV)';

    protected function typeRapport(): string
    {
        return 'trimestriel';
    }

    protected function periodeParDefaut(int $annee): string
    {
        return app(RapportPeriodeService::class)->trimestreCourant($annee);
    }
}
