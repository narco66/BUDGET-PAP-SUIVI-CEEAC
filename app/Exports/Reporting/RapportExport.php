<?php

namespace App\Exports\Reporting;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Classeur Excel d'un rapport : une feuille « Informations », puis une feuille par section.
 * Construit exclusivement depuis les SECTIONS normalisées (mêmes valeurs que PDF/Word).
 */
class RapportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $composed,
        private readonly array $meta,
    ) {
    }

    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new RapportInfoSheet($this->composed, $this->meta);

        foreach ($this->composed['sections'] as $i => $section) {
            $titre = ($i + 1).'. '.($section['titre'] ?? 'Section');

            if (($section['type'] ?? null) === 'table') {
                $sheets[] = new RapportSheet(
                    $titre,
                    array_map(fn ($c) => $c['label'], $section['colonnes']),
                    $section['lignes'],
                    $section['total'] ?? null,
                );
            } elseif (($section['type'] ?? null) === 'kpi') {
                $sheets[] = new RapportSheet($titre, ['Indicateur', 'Valeur'],
                    array_map(fn ($it) => [$it['label'], $it['valeur']], $section['items']));
            } elseif (($section['type'] ?? null) === 'liste') {
                $sheets[] = new RapportSheet($titre, ['Élément'], array_map(fn ($it) => [$it], $section['items']));
            } elseif (($section['type'] ?? null) === 'text') {
                $sheets[] = new RapportSheet($titre, ['Texte'], [[$section['contenu']]]);
            }
        }

        return $sheets;
    }
}
