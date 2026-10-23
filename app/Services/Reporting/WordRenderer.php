<?php

namespace App\Services\Reporting;

use App\Support\CharteCeeac;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/** Génère un document Word (.docx) à partir des sections normalisées d'un rapport. */
class WordRenderer
{
    /** @return string contenu binaire du .docx */
    public function rendre(array $composed, array $meta, ?string $qrDataUri): string
    {
        $word = new PhpWord();
        $word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language(\PhpOffice\PhpWord\Style\Language::FR_FR));

        $word->addTitleStyle(1, ['bold' => true, 'size' => 16, 'color' => CharteCeeac::primaireHex()]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 12, 'color' => CharteCeeac::primaireHex()]);
        $bleu = CharteCeeac::primaireHex();

        $section = $word->addSection(['marginTop' => 1100, 'marginBottom' => 900]);

        $section->addText(CharteCeeac::institution(), ['bold' => true, 'size' => 11, 'color' => $bleu]);
        $section->addText(CharteCeeac::sousTitre(), ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addText(CharteCeeac::systeme(), ['size' => 8, 'color' => CharteCeeac::fonceHex()]);
        $section->addTextBreak(1);
        $section->addTitle($composed['titre'], 1);
        $section->addText($composed['sous_titre'], ['size' => 11, 'color' => '444444']);
        $section->addText('Réf. '.$meta['reference'].' · version '.$meta['version'].' · généré le '.$meta['date'], ['size' => 8, 'color' => '888888']);
        $section->addTextBreak(1);

        foreach ($composed['sections'] as $i => $sec) {
            $titre = ($i + 1).'. '.($sec['titre'] ?? 'Section');

            match ($sec['type'] ?? 'text') {
                'kpi' => $this->kpi($section, $titre, $sec['items']),
                'table' => $this->table($section, $titre, $sec),
                'liste' => $this->liste($section, $titre, $sec['items']),
                default => $this->texte($section, $titre, $sec['contenu'] ?? ''),
            };
        }

        // Bloc signature + QR
        $section->addTextBreak(2);
        $sign = $section->addTable();
        $sign->addRow();
        $cellTexte = $sign->addCell(6000);
        $cellTexte->addText('Authenticité & signature', ['bold' => true, 'size' => 9]);
        if ($meta['signataire'] ?? null) {
            $cellTexte->addText('Signé par : '.$meta['signataire'], ['size' => 9]);
            $cellTexte->addText('Le : '.$meta['signe_le'], ['size' => 9]);
        } else {
            $cellTexte->addText('Document non signé électroniquement.', ['size' => 9, 'italic' => true]);
        }
        $cellTexte->addText('Vérification : '.$meta['verification'], ['size' => 8, 'color' => $bleu]);
        $cellTexte->addText(CharteCeeac::mentionPied(), ['size' => 7, 'color' => '888888']);
        $cellQr = $sign->addCell(3000);
        $qrFichier = $this->qrVersFichier($qrDataUri);
        if ($qrFichier) {
            $cellQr->addImage($qrFichier, ['width' => 90, 'height' => 90, 'alignment' => Jc::CENTER]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpt').'.docx';
        $word->save($tmp, 'Word2007');
        $contenu = (string) file_get_contents($tmp);
        @unlink($tmp);
        if ($qrFichier) {
            @unlink($qrFichier);
        }

        return $contenu;
    }

    /** Décode un data-URI PNG vers un fichier temporaire (PhpWord exige un chemin de fichier). */
    private function qrVersFichier(?string $dataUri): ?string
    {
        if (! $dataUri || ! str_contains($dataUri, 'base64,')) {
            return null;
        }
        $binaire = base64_decode(substr($dataUri, strpos($dataUri, 'base64,') + 7), true);
        if ($binaire === false) {
            return null;
        }
        $chemin = tempnam(sys_get_temp_dir(), 'qr').'.png';
        file_put_contents($chemin, $binaire);

        return $chemin;
    }

    private function kpi($section, string $titre, array $items): void
    {
        $section->addTitle($titre, 2);
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'DDDDDD', 'cellMargin' => 80]);
        foreach ($items as $it) {
            $table->addRow();
            $table->addCell(5000)->addText($it['label'], ['size' => 10]);
            $table->addCell(3000)->addText(Fmt::valeur($it['valeur'], $it['fmt'] ?? 'text'), ['bold' => true, 'size' => 10]);
        }
        $section->addTextBreak(1);
    }

    private function table($section, string $titre, array $sec): void
    {
        $section->addTitle($titre, 2);
        $cols = $sec['colonnes'];
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 60]);

        $table->addRow();
        foreach ($cols as $c) {
            $table->addCell(1500, ['bgColor' => CharteCeeac::primaireHex()])->addText($c['label'], ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
        }

        foreach ($sec['lignes'] as $ligne) {
            $table->addRow();
            foreach ($ligne as $j => $valeur) {
                $fmt = $cols[$j]['fmt'] ?? 'text';
                $table->addCell(1500)->addText(Fmt::valeur($valeur, $fmt), ['size' => 8]);
            }
        }

        if (! empty($sec['total'])) {
            $table->addRow();
            foreach ($sec['total'] as $j => $valeur) {
                $fmt = $cols[$j]['fmt'] ?? 'text';
                $table->addCell(1500, ['bgColor' => CharteCeeac::fondTableauHex()])->addText(Fmt::valeur($valeur, $fmt), ['bold' => true, 'size' => 8]);
            }
        }
        $section->addTextBreak(1);
    }

    private function liste($section, string $titre, array $items): void
    {
        $section->addTitle($titre, 2);
        foreach ($items as $it) {
            $section->addListItem((string) $it, 0, ['size' => 10]);
        }
        $section->addTextBreak(1);
    }

    private function texte($section, string $titre, string $contenu): void
    {
        $section->addTitle($titre, 2);
        $section->addText($contenu, ['size' => 10]);
        $section->addTextBreak(1);
    }
}
