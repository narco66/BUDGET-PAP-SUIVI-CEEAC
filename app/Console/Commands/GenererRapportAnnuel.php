<?php

namespace App\Console\Commands;

class GenererRapportAnnuel extends GenererRapportPeriodiqueCommand
{
    protected $signature = 'reporting:generer-annuel
                            {--exercice= : ID ou année de l\'exercice}
                            {--format=pdf : pdf, excel, word ou csv}';

    protected $description = 'Génère le rapport annuel institutionnel consolidé (PDF/Excel/Word/CSV)';

    protected function typeRapport(): string
    {
        return 'annuel';
    }

    protected function periodeParDefaut(int $annee): string
    {
        return (string) $annee;
    }
}
