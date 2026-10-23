<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

/** Génère la synthèse d'audit des recettes en PDF et Word (.docx) depuis le markdown source. */
class RecettesAuditDocCommand extends Command
{
    protected $signature = 'recettes:audit-doc {--source=docs/audit-recettes-2026-06-12.md : chemin du markdown source}';

    protected $description = 'Génère la synthèse d\'audit des recettes (PDF + Word) à partir du markdown';

    public function handle(): int
    {
        $source = base_path($this->option('source'));
        if (! is_file($source)) {
            $this->error("Source introuvable : {$source}");

            return self::FAILURE;
        }

        $markdown = (string) file_get_contents($source);
        $corps = Str::markdown($markdown, ['html_input' => 'allow']);
        $base = preg_replace('/\.md$/', '', $source);

        // --- PDF (DomPDF) ---
        $htmlPdf = $this->envelopper($corps);
        file_put_contents($base.'.pdf', Pdf::loadHTML($htmlPdf)->setPaper('a4', 'portrait')->output());
        $this->info('PDF généré : '.$base.'.pdf');

        // --- Word (.docx) ---
        $word = new PhpWord();
        $word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language(\PhpOffice\PhpWord\Style\Language::FR_FR));
        $section = $word->addSection(['marginTop' => 1000, 'marginBottom' => 900]);
        $section->addText('COMMISSION DE LA CEEAC', ['bold' => true, 'size' => 11, 'color' => '0A4A8F']);
        $section->addText('Communauté Économique des États de l\'Afrique Centrale', ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addTextBreak(1);
        Html::addHtml($section, $corps, false, false);
        $word->save($base.'.docx', 'Word2007');
        $this->info('Word généré : '.$base.'.docx');

        return self::SUCCESS;
    }

    private function envelopper(string $corps): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#222;}'
            .'h1{font-size:16px;color:#0a4a8f;}h2{font-size:12px;color:#0a4a8f;border-bottom:1px solid #0a4a8f;padding-bottom:2px;margin-top:14px;}'
            .'table{width:100%;border-collapse:collapse;margin:6px 0;}th,td{border:1px solid #ccc;padding:3px 5px;}th{background:#0a4a8f;color:#fff;}'
            .'code{background:#f0f0f0;padding:0 2px;}'
            .'</style></head><body>'.$corps.'</body></html>';
    }
}
