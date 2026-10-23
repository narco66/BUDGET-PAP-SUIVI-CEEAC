<?php

namespace App\Services\Reporting;

use App\Support\CharteCeeac;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Slide;

/**
 * Génère une présentation PowerPoint (.pptx) à partir des sections normalisées d'un rapport —
 * même source unique que les rendus PDF/Excel/Word/CSV, garantissant des chiffres identiques.
 * Destiné aux restitutions institutionnelles (cockpit exécutif, rapports trimestriel/annuel).
 */
class PptxRenderer
{
    private const LIGNES_TABLE_MAX = 12;

    /** @return string contenu binaire du .pptx */
    public function rendre(array $composed, array $meta, ?string $qrDataUri): string
    {
        $ppt = new PhpPresentation();
        $ppt->getDocumentProperties()
            ->setCreator(CharteCeeac::institution())
            ->setTitle((string) ($composed['titre'] ?? 'Rapport'));

        $this->slideTitre($ppt->getActiveSlide(), $composed, $meta);

        foreach ($composed['sections'] as $i => $sec) {
            $titre = ($i + 1).'. '.($sec['titre'] ?? 'Section');
            $corps = match ($sec['type'] ?? 'text') {
                'kpi' => $this->corpsKpi($sec['items'] ?? []),
                'table' => $this->corpsTable($sec),
                'liste' => $this->corpsListe($sec['items'] ?? []),
                default => (string) ($sec['contenu'] ?? ''),
            };
            $this->slideContenu($ppt->createSlide(), $titre, $corps);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpt').'.pptx';
        IOFactory::createWriter($ppt, 'PowerPoint2007')->save($tmp);
        $contenu = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $contenu;
    }

    private function slideTitre(Slide $slide, array $composed, array $meta): void
    {
        $bleu = CharteCeeac::primaireHex();
        $this->bloc($slide, CharteCeeac::institution(), 40, 40, 860, 40, 16, true, $bleu);
        $this->bloc($slide, (string) ($composed['titre'] ?? 'Rapport'), 40, 120, 860, 120, 30, true, $bleu);
        $this->bloc($slide, (string) ($composed['sous_titre'] ?? ''), 40, 260, 860, 60, 16, false, '444444');
        $this->bloc($slide, CharteCeeac::systeme(), 40, 330, 860, 30, 11, false, CharteCeeac::fonceHex());

        $pied = 'Réf. '.($meta['reference'] ?? '—').' · version '.($meta['version'] ?? '1').' · généré le '.($meta['date'] ?? '');
        if (! empty($meta['verification'])) {
            $pied .= "\nVérification : ".$meta['verification'];
        }
        $this->bloc($slide, $pied, 40, 400, 860, 70, 11, false, '888888');
    }

    private function slideContenu(Slide $slide, string $titre, string $corps): void
    {
        $this->bloc($slide, $titre, 40, 40, 860, 60, 20, true, CharteCeeac::primaireHex());
        $this->bloc($slide, $corps !== '' ? $corps : '—', 40, 120, 860, 420, 13, false, '333333');
    }

    /** @param array<int, array<string, mixed>> $items */
    private function corpsKpi(array $items): string
    {
        return implode("\n", array_map(
            static fn ($it) => '• '.($it['label'] ?? '').' : '.Fmt::valeur($it['valeur'] ?? '', $it['fmt'] ?? 'text'),
            $items,
        ));
    }

    private function corpsTable(array $sec): string
    {
        $cols = $sec['colonnes'] ?? [];
        $entete = implode('  |  ', array_map(static fn ($c) => (string) ($c['label'] ?? ''), $cols));
        $lignes = [$entete];

        $toutes = $sec['lignes'] ?? [];
        foreach (array_slice($toutes, 0, self::LIGNES_TABLE_MAX) as $ligne) {
            $cellules = [];
            foreach ($ligne as $j => $valeur) {
                $cellules[] = Fmt::valeur($valeur, $cols[$j]['fmt'] ?? 'text');
            }
            $lignes[] = implode('  |  ', $cellules);
        }

        $reste = count($toutes) - self::LIGNES_TABLE_MAX;
        if ($reste > 0) {
            $lignes[] = '… (+'.$reste.' ligne(s) — voir l’export tableur)';
        }

        if (! empty($sec['total'])) {
            $total = [];
            foreach ($sec['total'] as $j => $valeur) {
                $total[] = Fmt::valeur($valeur, $cols[$j]['fmt'] ?? 'text');
            }
            $lignes[] = 'TOTAL : '.implode('  |  ', $total);
        }

        return implode("\n", $lignes);
    }

    /** @param array<int, mixed> $items */
    private function corpsListe(array $items): string
    {
        return implode("\n", array_map(static fn ($it) => '• '.(string) $it, $items));
    }

    private function bloc(Slide $slide, string $contenu, int $x, int $y, int $w, int $h, int $taille, bool $gras, string $couleur): void
    {
        $shape = $slide->createRichTextShape()
            ->setHeight($h)->setWidth($w)->setOffsetX($x)->setOffsetY($y);

        foreach (explode("\n", $contenu) as $k => $ligne) {
            $para = $k === 0 ? $shape->getActiveParagraph() : $shape->createParagraph();
            $run = $para->createTextRun($ligne);
            $run->getFont()->setSize($taille)->setBold($gras)->getColor()->setRGB($couleur);
        }
    }
}
