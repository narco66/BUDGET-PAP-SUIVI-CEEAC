<?php

namespace App\Services\Strategie;

use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

/** Charge le manuel utilisateur (markdown) et le convertit pour l'affichage web / PDF / Word. */
class StrategieManuelService
{
    private const SOURCE = 'docs/manuel-utilisateur-planification-strategique.md';

    public function cheminSource(): string
    {
        return base_path(self::SOURCE);
    }

    public function titre(): string
    {
        return 'Manuel utilisateur — Planification stratégique';
    }

    /** @return string HTML du corps (sans enveloppe document) */
    public function corpsHtml(): string
    {
        $markdown = $this->markdown();

        return Str::markdown($markdown, ['html_input' => 'strip']);
    }

    public function enveloppePdf(string $corps): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#222;line-height:1.4;}'
            .'h1{font-size:16px;color:#0a4a8f;}h2{font-size:12px;color:#0a4a8f;border-bottom:1px solid #0a4a8f;padding-bottom:2px;margin-top:14px;}'
            .'h3{font-size:11px;color:#333;margin-top:10px;}table{width:100%;border-collapse:collapse;margin:6px 0;}'
            .'th,td{border:1px solid #ccc;padding:3px 5px;font-size:9px;}th{background:#0a4a8f;color:#fff;}'
            .'code{background:#f0f0f0;padding:0 2px;font-size:9px;}pre{background:#f5f5f5;padding:6px;font-size:8px;}'
            .'blockquote{border-left:3px solid #0a4a8f;margin:6px 0;padding-left:8px;color:#444;}'
            .'ul,ol{margin:4px 0 4px 16px;}a{color:#0a4a8f;text-decoration:none;}'
            .'</style></head><body>'.$corps.'</body></html>';
    }

    /** @return string contenu binaire du .docx */
    public function contenuWord(): string
    {
        $corps = $this->corpsHtml();
        $word = new PhpWord();
        $word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language(\PhpOffice\PhpWord\Style\Language::FR_FR));
        $section = $word->addSection(['marginTop' => 1000, 'marginBottom' => 900]);
        $section->addText('COMMISSION DE LA CEEAC', ['bold' => true, 'size' => 11, 'color' => '0A4A8F']);
        $section->addText('Communauté Économique des États de l\'Afrique Centrale', ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addTextBreak(1);
        Html::addHtml($section, $corps, false, false);

        $tmp = tempnam(sys_get_temp_dir(), 'manuel-strategie').'.docx';
        $word->save($tmp, 'Word2007');
        $contenu = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $contenu;
    }

    private function markdown(): string
    {
        $chemin = $this->cheminSource();
        abort_unless(is_file($chemin), 404, 'Manuel utilisateur introuvable.');

        return (string) file_get_contents($chemin);
    }
}
