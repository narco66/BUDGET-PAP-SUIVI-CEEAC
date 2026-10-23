<?php

namespace App\Console\Commands;

use App\Services\Reporting\RapportPeriodeService;

class GenererRapportSemestriel extends GenererRapportPeriodiqueCommand
{
    protected $signature = 'reporting:generer-semestre
                            {--exercice= : ID ou année de l\'exercice}
                            {--periode= : Période ex. 2026-S1 (défaut : semestre courant)}
                            {--format=pdf : pdf, excel, word ou csv}';

    protected $description = 'Génère le rapport semestriel institutionnel (PDF/Excel/Word/CSV)';

    protected function typeRapport(): string
    {
        return 'semestriel';
    }

    protected function periodeParDefaut(int $annee): string
    {
        return app(RapportPeriodeService::class)->semestreCourant($annee);
    }
}
