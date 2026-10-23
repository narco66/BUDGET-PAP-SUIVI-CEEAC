<?php

namespace App\Exports\Reporting;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export CSV d'un rapport : aplatit la section « table » la plus détaillée des sections normalisées
 * (le CSV étant mono-feuille). Mêmes valeurs que les rendus PDF / Excel / Word.
 */
class RapportCsv implements FromArray, WithHeadings
{
    private array $colonnes = [];

    private array $lignes = [];

    public function __construct(array $composed)
    {
        $meilleure = null;
        foreach ($composed['sections'] as $section) {
            if (($section['type'] ?? null) === 'table'
                && (! $meilleure || count($section['lignes']) > count($meilleure['lignes']))) {
                $meilleure = $section;
            }
        }
        if ($meilleure) {
            $this->colonnes = array_map(fn ($c) => $c['label'], $meilleure['colonnes']);
            $this->lignes = $meilleure['lignes'];
        }
    }

    public function array(): array
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return $this->colonnes;
    }
}
