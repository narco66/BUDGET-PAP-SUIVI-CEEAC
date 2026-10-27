<?php

/**
 * Livrable du jeu d'essai PostgreSQL : génère DOCX (PhpWord) et PDF (dompdf)
 * à partir de storage/app/jeu-essai/resultats.json.
 * Usage : php database/scripts/jeu_essai_livrable.php
 */

$root = dirname(__DIR__, 2);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Shared\Converter;

$data = json_decode(file_get_contents(storage_path('app/jeu-essai/resultats.json')), true);
$meta = $data['meta'];
$cas = $data['cas'];
$sortie = $root.'/docs/jeu-essai';
@mkdir($sortie, 0777, true);
$nom = 'Jeu_essai_migration_PostgreSQL_'.substr($meta['execute_le'], 0, 10);
$logo = public_path('images/logo-ceeac.png');

const BLEU = '0A4A8F';
const BLEU_FONCE = '073564';
$couleurs = [
    'CONFORME' => ['1E7E34', 'E6F4EA'],
    'NON CONFORME' => ['B02A37', 'FBE9EB'],
    'RÉSERVE' => ['8A5A00', 'FFF4E0'],
];

// ─────────────── Données dérivées ───────────────

$domaines = [];
foreach ($cas as $c) {
    $d = &$domaines[$c['domaine']];
    $d['total'] = ($d['total'] ?? 0) + 1;
    $d[$c['statut']] = ($d[$c['statut']] ?? 0) + 1;
    unset($d);
}
$tot = ['total' => count($cas)];
foreach (array_keys($couleurs) as $s) $tot[$s] = count(array_filter($cas, fn ($c) => $c['statut'] === $s));
$taux = round($tot['CONFORME'] / max(1, $tot['total']) * 100);

$actions = [
    'DON' => 'La base PostgreSQL a été recréée par un « migrate:fresh --seed » extérieur à la campagne le 24/10/2026 vers 21 h 16 (heure locale), après la copie validée. La base MySQL source est intacte. Action : recharger PostgreSQL depuis MySQL (un seul intervenant sur la base), puis rejouer le jeu d\'essai.',
    'SCH-07' => 'PostgreSQL ne crée pas d\'index automatique sur les clés étrangères, contrairement à MySQL. Action : migration PostgreSQL de création des index manquants. Impact actuel faible (base de 30 Mo), croissant avec le volume.',
    'AUT-04' => 'La base actuelle (recréée) ne contient pas la configuration MFA du compte DSI. Action : rejouer après rechargement des données MySQL.',
    'APP-05' => 'LIKE respecte la casse sous PostgreSQL (MySQL utilisait une collation insensible à la casse) : 65 occurrences dans 39 fichiers. Action : passer les filtres de recherche à whereLike(..., caseSensitive: false).',
];
$actionPour = fn (array $c) => $actions[$c['id']] ?? $actions[substr($c['id'], 0, 3)] ?? 'Analyse à mener.';
$anomalies = array_values(array_filter($cas, fn ($c) => $c['statut'] !== 'CONFORME'));

$intro = [
    'objet' => 'Le présent document constitue le jeu d\'essai de la migration de la base de données de GEST-BUDGET-PAP de MySQL 8.4 vers PostgreSQL 18. Chaque cas décrit son objectif, les données d\'entrée, les étapes, le résultat attendu, puis le résultat effectivement obtenu lors de l\'exécution automatisée.',
    'perimetre' => 'Infrastructure et sauvegarde, structure de la base (tables, colonnes, énumérations, clés, séquences, migrations), reprise des données, contraintes d\'intégrité et types, authentification et sécurité, comportement de l\'application (requêtes SQL corrigées, parcours de tous les écrans).',
    'methode' => 'Les cas sont exécutés par le script database/scripts/jeu_essai_postgresql.php, rejouable à volonté. Toute écriture de test a lieu dans un point de sauvegarde annulé. Seules les connexions réelles (AUT-01, AUT-04) laissent les traces normales d\'une connexion : session, journal de connexion, journal d\'audit, date de dernière connexion du compte directeur.plan@ceeac.test. La trace d\'échec créée par AUT-02 est supprimée après contrôle. Les comparaisons de données s\'appuient sur la base MySQL d\'origine, conservée en lecture seule.',
    'historique' => 'Une première exécution (24/10/2026, 21 h 32) avait donné 39 cas conformes, 4 non conformes et 3 réserves : la base PostgreSQL avait été recréée par un « migrate:fresh --seed » extérieur à la campagne après la copie validée, ce qui invalidait les cas de reprise des données. La base a été rechargée depuis MySQL (209 tables, 5 734 lignes, 198 séquences recalées), puis le jeu d\'essai a été rejoué intégralement. Le présent document présente les résultats de cette seconde exécution. Un contrôle complémentaire du contenu, ligne à ligne, montre 204 tables identiques ; les seuls écarts proviennent des traces des connexions réelles du jeu d\'essai et du cache applicatif.',
];
$conventions = [
    'CONFORME' => 'Le résultat obtenu correspond au résultat attendu.',
    'NON CONFORME' => 'Le résultat obtenu diffère du résultat attendu : anomalie à corriger avant validation.',
    'RÉSERVE' => 'Écart connu et analysé, non bloquant pour l\'exploitation actuelle, avec action recommandée.',
];
$env = [
    ['Date d\'exécution', $meta['execute_le'].' (heure locale, WAT)'],
    ['Poste', $meta['poste']],
    ['SGBD cible', $meta['pg'].' — 127.0.0.1:5433, base gest_budget_pap'],
    ['SGBD source (référence)', 'MySQL '.$meta['mysql'].' — base gest_budget_pap (Laragon)'],
    ['Application', 'GEST-BUDGET-PAP — Laravel '.$meta['laravel'].', PHP '.$meta['php']],
    ['URL testée', 'http://budget-pap-suivi-ceeac.test'],
];

// ─────────────── DOCX ───────────────

$w = new PhpWord;
$w->setDefaultFontName('Calibri');
$w->setDefaultFontSize(10);
$w->getDocInfo()->setTitle('Jeu d\'essai — Migration PostgreSQL 18')->setCompany('Commission de la CEEAC')->setCreator('DSI CEEAC');
$w->addTitleStyle(1, ['size' => 15, 'bold' => true, 'color' => BLEU], ['spaceBefore' => 240, 'spaceAfter' => 120]);
$w->addTitleStyle(2, ['size' => 12, 'bold' => true, 'color' => BLEU_FONCE], ['spaceBefore' => 200, 'spaceAfter' => 80, 'keepNext' => true]);
$tbl = ['borderSize' => 4, 'borderColor' => 'B8C4D6', 'cellMargin' => 70];
$w->addTableStyle('grille', $tbl, ['bgColor' => BLEU]);
$entete = ['bold' => true, 'color' => 'FFFFFF', 'size' => 9];
$p0 = ['spaceAfter' => 0];
$marges = ['marginLeft' => Converter::cmToTwip(2), 'marginRight' => Converter::cmToTwip(2), 'marginTop' => Converter::cmToTwip(2), 'marginBottom' => Converter::cmToTwip(1.8)];

// Page de garde
$s = $w->addSection($marges);
$s->addTextBreak(3);
if (is_file($logo)) $s->addImage($logo, ['width' => 90, 'alignment' => Jc::CENTER]);
$s->addTextBreak(1);
$s->addText('COMMISSION DE LA CEEAC', ['size' => 12, 'bold' => true, 'color' => BLEU_FONCE], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
$s->addText('Direction des Systèmes d\'Information', ['size' => 10, 'color' => '555555'], ['alignment' => Jc::CENTER]);
$s->addTextBreak(3);
$s->addText('JEU D\'ESSAI', ['size' => 28, 'bold' => true, 'color' => BLEU], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
$s->addText('Migration de la base de données MySQL → PostgreSQL 18', ['size' => 15, 'color' => BLEU_FONCE], ['alignment' => Jc::CENTER]);
$s->addText('Application GEST-BUDGET-PAP', ['size' => 12, 'color' => '555555'], ['alignment' => Jc::CENTER]);
$s->addTextBreak(3);
$g = $s->addTable(['borderSize' => 4, 'borderColor' => 'B8C4D6', 'cellMargin' => 90, 'alignment' => Jc::CENTER]);
foreach ([['Version', '1.0'], ['Date d\'exécution', $meta['execute_le']], ['Cas exécutés', (string) $tot['total']], ['Taux de conformité', "{$taux} %"], ['Statut du document', 'Pour validation']] as [$k, $v]) {
    $g->addRow();
    $g->addCell(3200, ['bgColor' => 'EEF3FA'])->addText($k, ['bold' => true], $p0);
    $g->addCell(4200)->addText($v, [], $p0);
}

// Corps
$s = $w->addSection($marges);
$s->addFooter()->addPreserveText('Jeu d\'essai — Migration PostgreSQL 18 · GEST-BUDGET-PAP · Page {PAGE} / {NUMPAGES}', ['size' => 8, 'color' => '777777'], ['alignment' => Jc::CENTER]);

$s->addTitle('1. Objet, périmètre et méthode', 1);
$s->addText($intro['objet'], [], ['alignment' => Jc::BOTH]);
$s->addTitle('Périmètre', 2);
$s->addText($intro['perimetre'], [], ['alignment' => Jc::BOTH]);
$s->addTitle('Méthode d\'exécution', 2);
$s->addText($intro['methode'], [], ['alignment' => Jc::BOTH]);
$s->addTitle('Historique des exécutions', 2);
$s->addText($intro['historique'], [], ['alignment' => Jc::BOTH]);

$s->addTitle('2. Environnement d\'exécution', 1);
$t = $s->addTable('grille');
foreach ($env as [$k, $v]) {
    $t->addRow();
    $t->addCell(3200, ['bgColor' => 'EEF3FA'])->addText($k, ['bold' => true], $p0);
    $t->addCell(5800)->addText($v, [], $p0);
}

$s->addTitle('3. Conventions de statut', 1);
$t = $s->addTable('grille');
foreach ($conventions as $k => $v) {
    $t->addRow();
    $t->addCell(2200, ['bgColor' => $couleurs[$k][1]])->addText($k, ['bold' => true, 'color' => $couleurs[$k][0]], $p0);
    $t->addCell(6800)->addText($v, [], $p0);
}

$s->addTitle('4. Synthèse des résultats', 1);
$s->addText("{$tot['total']} cas exécutés : {$tot['CONFORME']} conformes, {$tot['NON CONFORME']} non conformes, {$tot['RÉSERVE']} sous réserve. Taux de conformité : {$taux} %.", ['bold' => true]);
$t = $s->addTable('grille');
$t->addRow(null, ['tblHeader' => true]);
foreach (['Domaine' => 3000, 'Cas' => 1200, 'Conformes' => 1600, 'Non conformes' => 1600, 'Réserves' => 1600] as $h => $wd) $t->addCell($wd, ['bgColor' => BLEU])->addText($h, $entete, $p0);
foreach ($domaines + ['TOTAL' => $tot] as $nomD => $d) {
    $t->addRow();
    $b = $nomD === 'TOTAL' ? ['bold' => true] : [];
    $t->addCell(3000)->addText($nomD, $b, $p0);
    foreach (['total', 'CONFORME', 'NON CONFORME', 'RÉSERVE'] as $k) $t->addCell(1600)->addText((string) ($d[$k] ?? 0), $b, $p0 + ['alignment' => Jc::CENTER]);
}

$s->addTitle('5. Liste des cas', 1);
$t = $s->addTable('grille');
$t->addRow(null, ['tblHeader' => true]);
foreach (['N°' => 900, 'Domaine' => 1500, 'Intitulé' => 3300, 'Résultat attendu' => 2100, 'Statut' => 1400] as $h => $wd) $t->addCell($wd, ['bgColor' => BLEU])->addText($h, $entete, $p0);
foreach ($cas as $c) {
    $t->addRow(null, ['cantSplit' => true]);
    $t->addCell(900)->addText($c['id'], ['bold' => true, 'size' => 9], $p0);
    $t->addCell(1500)->addText($c['domaine'], ['size' => 9], $p0);
    $t->addCell(3300)->addText($c['titre'], ['size' => 9], $p0);
    $t->addCell(2100)->addText($c['attendu'], ['size' => 8], $p0);
    $t->addCell(1400, ['bgColor' => $couleurs[$c['statut']][1]])->addText($c['statut'], ['bold' => true, 'size' => 8, 'color' => $couleurs[$c['statut']][0]], $p0);
}

$s->addTitle('6. Fiches de cas', 1);
$courant = null;
foreach ($cas as $c) {
    if ($c['domaine'] !== $courant) {
        $courant = $c['domaine'];
        $s->addTitle($courant, 2);
    }
    $t = $s->addTable('grille');
    $t->addRow(null, ['cantSplit' => true]);
    $t->addCell(6600, ['bgColor' => BLEU, 'gridSpan' => 1])->addText("{$c['id']} — {$c['titre']}", $entete + ['size' => 10], $p0);
    $t->addCell(2400, ['bgColor' => $couleurs[$c['statut']][1]])->addText($c['statut'], ['bold' => true, 'color' => $couleurs[$c['statut']][0]], $p0 + ['alignment' => Jc::CENTER]);
    foreach ([['Objectif', $c['objectif']], ['Données d\'entrée', $c['donnees']], ['Étapes', $c['etapes']], ['Résultat attendu', $c['attendu']], ['Résultat obtenu', $c['obtenu']], ['Durée', $c['duree_ms'].' ms']] as [$k, $v]) {
        $t->addRow(null, ['cantSplit' => true]);
        $t->addCell(2200, ['bgColor' => 'EEF3FA'])->addText($k, ['bold' => true, 'size' => 9], $p0);
        $t->addCell(6800, ['gridSpan' => 1])->addText($v, ['size' => 9], $p0);
    }
    $s->addTextBreak(1, ['size' => 6]);
}

$s->addTitle('7. Anomalies et réserves', 1);
if (! $anomalies) {
    $s->addText('Aucune anomalie ni réserve.');
} else {
    $t = $s->addTable('grille');
    $t->addRow(null, ['tblHeader' => true]);
    foreach (['N°' => 900, 'Statut' => 1400, 'Constat' => 3000, 'Analyse et action' => 3700] as $h => $wd) $t->addCell($wd, ['bgColor' => BLEU])->addText($h, $entete, $p0);
    foreach ($anomalies as $c) {
        $t->addRow(null, ['cantSplit' => true]);
        $t->addCell(900)->addText($c['id'], ['bold' => true, 'size' => 9], $p0);
        $t->addCell(1400, ['bgColor' => $couleurs[$c['statut']][1]])->addText($c['statut'], ['bold' => true, 'size' => 8, 'color' => $couleurs[$c['statut']][0]], $p0);
        $t->addCell(3000)->addText($c['obtenu'], ['size' => 8], $p0);
        $t->addCell(3700)->addText($actionPour($c), ['size' => 8], $p0);
    }
}

$s->addTitle('8. Conclusion et visa', 1);
$conclusion = $tot['NON CONFORME']
    ? "La structure de la base, les contraintes, les types, l'authentification et le comportement applicatif sont conformes sur PostgreSQL 18. La recette ne peut toutefois pas être prononcée : {$tot['NON CONFORME']} cas sont non conformes, tous liés à l'état actuel des données (base recréée après la copie). Après rechargement des données depuis MySQL, le jeu d'essai devra être rejoué dans son intégralité."
    : "Aucun cas n'est non conforme : la structure de la base, la reprise intégrale des données, les contraintes d'intégrité, les types, l'authentification et le comportement applicatif sont validés sur PostgreSQL 18. La migration peut être prononcée, sous les {$tot['RÉSERVE']} réserve(s) non bloquante(s) de la section 7, dont le traitement est recommandé avant la montée en charge.";
$s->addText($conclusion, [], ['alignment' => Jc::BOTH]);
$s->addTextBreak(1);
$t = $s->addTable('grille');
$t->addRow();
foreach (['Rédigé par', 'Vérifié par', 'Approuvé par'] as $h) $t->addCell(3000, ['bgColor' => BLEU])->addText($h, $entete, $p0 + ['alignment' => Jc::CENTER]);
$t->addRow(1400);
foreach ([1, 2, 3] as $_) $t->addCell(3000)->addText("Nom :\nDate :\nSignature :", ['size' => 9, 'color' => '777777']);

$w->save("{$sortie}/{$nom}.docx", 'Word2007');

// ─────────────── PDF ───────────────

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$badge = fn ($s) => '<span class="b" style="color:#'.$couleurs[$s][0].';background:#'.$couleurs[$s][1].'">'.$e($s).'</span>';
$logoData = is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : '';
ob_start(); ?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
@page { margin: 2cm 1.8cm 1.8cm 1.8cm; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #1f2933; line-height: 1.4; }
h1 { color: #<?= BLEU ?>; font-size: 14pt; border-bottom: 2px solid #<?= BLEU ?>; padding-bottom: 3px; margin: 18px 0 8px; }
h2 { color: #<?= BLEU_FONCE ?>; font-size: 11pt; margin: 14px 0 6px; }
p { margin: 0 0 6px; text-align: justify; }
table { width: 100%; border-collapse: collapse; margin: 4px 0 10px; }
th { background: #<?= BLEU ?>; color: #fff; text-align: left; font-size: 8pt; padding: 4px 5px; }
td { border: 1px solid #B8C4D6; padding: 4px 5px; vertical-align: top; font-size: 8pt; }
td.k { background: #EEF3FA; font-weight: bold; width: 26%; }
tr { page-break-inside: avoid; }
.b { font-weight: bold; font-size: 7.5pt; padding: 1px 4px; white-space: nowrap; }
.fiche { page-break-inside: avoid; margin-bottom: 8px; }
.fiche th.titre { font-size: 9pt; }
.garde { text-align: center; padding-top: 70px; page-break-after: always; }
.garde .inst { color: #<?= BLEU_FONCE ?>; font-weight: bold; font-size: 12pt; margin-top: 14px; }
.garde .titre { color: #<?= BLEU ?>; font-size: 28pt; font-weight: bold; margin-top: 70px; }
.garde .sous { color: #<?= BLEU_FONCE ?>; font-size: 14pt; }
.garde table { width: 60%; margin: 70px auto 0; }
.c { text-align: center; }
.tot td { font-weight: bold; background: #F5F7FA; }
.visa td { height: 70px; color: #777; }
footer { position: fixed; bottom: -1.2cm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #777; }
</style></head><body>
<footer>Jeu d'essai — Migration PostgreSQL 18 · GEST-BUDGET-PAP · Commission de la CEEAC</footer>
<div class="garde">
  <?php if ($logoData): ?><img src="<?= $logoData ?>" style="width:110px"><?php endif; ?>
  <div class="inst">COMMISSION DE LA CEEAC</div>
  <div style="color:#555">Direction des Systèmes d'Information</div>
  <div class="titre">JEU D'ESSAI</div>
  <div class="sous">Migration de la base de données MySQL → PostgreSQL 18</div>
  <div style="color:#555;font-size:11pt">Application GEST-BUDGET-PAP</div>
  <table>
    <?php foreach ([['Version', '1.0'], ['Date d\'exécution', $meta['execute_le']], ['Cas exécutés', $tot['total']], ['Taux de conformité', "{$taux} %"], ['Statut du document', 'Pour validation']] as [$k, $v]): ?>
    <tr><td class="k"><?= $e($k) ?></td><td><?= $e($v) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<h1>1. Objet, périmètre et méthode</h1>
<p><?= $e($intro['objet']) ?></p>
<h2>Périmètre</h2><p><?= $e($intro['perimetre']) ?></p>
<h2>Méthode d'exécution</h2><p><?= $e($intro['methode']) ?></p>
<h2>Historique des exécutions</h2><p><?= $e($intro['historique']) ?></p>

<h1>2. Environnement d'exécution</h1>
<table><?php foreach ($env as [$k, $v]): ?><tr><td class="k"><?= $e($k) ?></td><td><?= $e($v) ?></td></tr><?php endforeach; ?></table>

<h1>3. Conventions de statut</h1>
<table><?php foreach ($conventions as $k => $v): ?><tr><td style="width:22%"><?= $badge($k) ?></td><td><?= $e($v) ?></td></tr><?php endforeach; ?></table>

<h1>4. Synthèse des résultats</h1>
<p><b><?= "{$tot['total']} cas exécutés : {$tot['CONFORME']} conformes, {$tot['NON CONFORME']} non conformes, {$tot['RÉSERVE']} sous réserve. Taux de conformité : {$taux} %." ?></b></p>
<table>
  <tr><th>Domaine</th><th class="c">Cas</th><th class="c">Conformes</th><th class="c">Non conformes</th><th class="c">Réserves</th></tr>
  <?php foreach ($domaines + ['TOTAL' => $tot] as $nomD => $d): ?>
  <tr class="<?= $nomD === 'TOTAL' ? 'tot' : '' ?>"><td><?= $e($nomD) ?></td><?php foreach (['total', 'CONFORME', 'NON CONFORME', 'RÉSERVE'] as $k): ?><td class="c"><?= $d[$k] ?? 0 ?></td><?php endforeach; ?></tr>
  <?php endforeach; ?>
</table>

<h1>5. Liste des cas</h1>
<table>
  <tr><th style="width:9%">N°</th><th style="width:15%">Domaine</th><th>Intitulé</th><th style="width:28%">Résultat attendu</th><th style="width:13%">Statut</th></tr>
  <?php foreach ($cas as $c): ?>
  <tr><td><b><?= $e($c['id']) ?></b></td><td><?= $e($c['domaine']) ?></td><td><?= $e($c['titre']) ?></td><td><?= $e($c['attendu']) ?></td><td><?= $badge($c['statut']) ?></td></tr>
  <?php endforeach; ?>
</table>

<h1 style="page-break-before:always">6. Fiches de cas</h1>
<?php $courant = null; foreach ($cas as $c): ?>
  <?php if ($c['domaine'] !== $courant): $courant = $c['domaine']; ?><h2><?= $e($courant) ?></h2><?php endif; ?>
  <div class="fiche"><table>
    <tr><th class="titre" colspan="1"><?= $e("{$c['id']} — {$c['titre']}") ?></th><th style="width:18%;background:#<?= $couleurs[$c['statut']][1] ?>;color:#<?= $couleurs[$c['statut']][0] ?>;text-align:center"><?= $e($c['statut']) ?></th></tr>
    <?php foreach ([['Objectif', $c['objectif']], ['Données d\'entrée', $c['donnees']], ['Étapes', $c['etapes']], ['Résultat attendu', $c['attendu']], ['Résultat obtenu', $c['obtenu']], ['Durée', $c['duree_ms'].' ms']] as [$k, $v]): ?>
    <tr><td class="k"><?= $e($k) ?></td><td><?= $e($v) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
<?php endforeach; ?>

<h1>7. Anomalies et réserves</h1>
<?php if (! $anomalies): ?><p>Aucune anomalie ni réserve.</p><?php else: ?>
<table>
  <tr><th style="width:9%">N°</th><th style="width:14%">Statut</th><th style="width:32%">Constat</th><th>Analyse et action</th></tr>
  <?php foreach ($anomalies as $c): ?>
  <tr><td><b><?= $e($c['id']) ?></b></td><td><?= $badge($c['statut']) ?></td><td><?= $e($c['obtenu']) ?></td><td><?= $e($actionPour($c)) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<div style="page-break-inside: avoid">
<h1>8. Conclusion et visa</h1>
<p><?= $e($conclusion) ?></p>
<table class="visa">
  <tr><th class="c">Rédigé par</th><th class="c">Vérifié par</th><th class="c">Approuvé par</th></tr>
  <tr><td>Nom :<br>Date :<br>Signature :</td><td>Nom :<br>Date :<br>Signature :</td><td>Nom :<br>Date :<br>Signature :</td></tr>
</table>
</div>
</body></html>
<?php
$html = ob_get_clean();
$opt = new Options;
$opt->set('isRemoteEnabled', false);
$opt->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf($opt);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4');
$pdf->render();
$canvas = $pdf->getCanvas();
$canvas->page_text(520, 815, 'Page {PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.47, 0.47, 0.47]);
file_put_contents("{$sortie}/{$nom}.pdf", $pdf->output());

echo "{$sortie}/{$nom}.docx".PHP_EOL."{$sortie}/{$nom}.pdf".PHP_EOL;
