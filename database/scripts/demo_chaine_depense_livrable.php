<?php

/**
 * Livrable du jeu d'essai de démonstration de la chaîne de la dépense : DOCX (PhpWord) et PDF (dompdf),
 * générés à partir de storage/app/demo-chaine-depense/resultats.json.
 * Usage : php database/scripts/demo_chaine_depense_livrable.php
 */

$root = dirname(__DIR__, 2);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

$data = json_decode(file_get_contents(storage_path('app/demo-chaine-depense/resultats.json')), true);
$meta = $data['meta'];
$scenarios = $data['scenarios'];
$sc = collect($scenarios)->keyBy('id');
$sortie = $root.'/docs/jeu-essai';
@mkdir($sortie, 0777, true);
$nom = 'Jeu_essai_demo_chaine_depense_'.substr($meta['execute_le'], 0, 10);
$logo = public_path('images/logo-ceeac.png');

const BLEU = '0A4A8F';
const BLEU_FONCE = '073564';
const COULEURS = [
    'CONFORME' => ['1E7E34', 'E6F4EA'],
    'NON CONFORME' => ['B02A37', 'FBE9EB'],
    'RÉSERVE' => ['8A5A00', 'FFF4E0'],
    'CORRIGÉ' => ['0A4A8F', 'E8F0FA'],
    'À NOTER' => ['555555', 'F0F0F0'],
];
$xaf = fn ($m) => $m === null || $m === '' ? '' : number_format((float) $m, 0, ',', ' ').' XAF';
$statutEtape = fn ($v) => $v === true ? 'CONFORME' : ($v === 'reserve' ? 'RÉSERVE' : 'NON CONFORME');

// ───────────────────────── Données dérivées ─────────────────────────

$familles = [];
foreach ($scenarios as $s) {
    $f = &$familles[$s['famille']];
    $f['total'] = ($f['total'] ?? 0) + 1;
    $f[$s['statut']] = ($f[$s['statut']] ?? 0) + 1;
    unset($f);
}
$tot = ['total' => count($scenarios)];
foreach (['CONFORME', 'NON CONFORME', 'RÉSERVE'] as $k) {
    $tot[$k] = count(array_filter($scenarios, fn ($s) => $s['statut'] === $k));
}
$nbEtapes = array_sum(array_map(fn ($s) => count($s['etapes']), $scenarios));
$nbControles = array_sum(array_map(fn ($s) => count($s['controles']), $scenarios));

$num = fn (string $id, string $cle) => $sc[$id]['refs'][$cle]['numero'] ?? '—';
$pieces = function (array $s) use ($xaf) {
    $libelles = ['EB' => 'EB', 'ENG' => 'ENG', 'LIQ' => 'LIQ', 'LIQ_T1' => 'LIQ tranche 1', 'LIQ_T2' => 'LIQ tranche 2', 'ORD' => 'ORD', 'ORD_T1' => 'ORD tranche 1', 'ORD_T2' => 'ORD tranche 2', 'OP' => 'Ordre de paiement', 'PAY' => 'PAY (auto)', 'PAY_1' => 'PAY 1', 'PAY_2' => 'PAY 2', 'PAY_MANUEL' => 'PAY saisi', 'PAY_CHEQUE' => 'PAY chèque', 'PAY_DEVISE' => 'PAY devise'];
    $lignes = [];
    foreach ($libelles as $k => $l) {
        $r = $s['refs'][$k] ?? null;
        if (is_array($r) && isset($r['numero'])) {
            $lignes[] = [$l, $r['numero'], trim($r['statut'].($r['etat'] ? ' / '.$r['etat'] : '')), $xaf($r['montant'])];
        }
    }

    return $lignes;
};

$comptes = [
    ['Point focal', 'point_focal@ceeac.test', 'Prépare les expressions de besoin', 'Non'],
    ['Chef de service', 'chef_service@ceeac.test', 'Soumet les EB, certifie le service fait', 'Non'],
    ['Chef du Service Moyens Généraux', 'chef_service_mg@ceeac.test', 'Certificateur suppléant du service fait (LIQ-06)', 'Non'],
    ['Directeur (Budget / DRHMG)', 'directeur@ceeac.test', '1er visa EB ; visa Direction du Budget à l\'ENG', 'Non'],
    ['Commissaire', 'commissaire@ceeac.test', '2e visa EB PAP technique', 'Oui'],
    ['Secrétaire général', 'secretaire_general@ceeac.test', 'Visa EB appui / hors PAP ; ordonnateur ≤ 5 M', 'Oui'],
    ['Président', 'president@ceeac.test', 'Approbation hors PAP ; ordonnateur > 5 M', 'Non'],
    ['Gestionnaire budgétaire', 'gestionnaire_budgetaire@ceeac.test', 'Soumet ENG, saisit LIQ, ORD, PAY', 'Non'],
    ['Contrôleur financier', 'controleur_financier@ceeac.test', 'Visa ENG et LIQ', 'Oui'],
    ['Comptable', 'comptable@ceeac.test', 'Contrôles comptables (1re étape PAY) — nom affiché « Agent Comptable »', 'Non'],
    ['Agent comptable', 'agent_comptable@ceeac.test', 'Autorisation de décaissement (2e étape PAY)', 'Non'],
    ['Directeur de la Planification', 'directeur.plan@ceeac.test', 'Délégataire de signature du SG (NOM-06)', 'Non'],
];

$circuits = DB::table('workflows as w')->join('workflow_etapes as e', 'e.workflow_id', '=', 'w.id')
    ->whereIn('w.code', ['BESOIN_PAP', 'BESOIN_PAP_APPUI', 'BESOIN_HORS_PAP', 'ENGAGEMENT', 'LIQUIDATION', 'ORDONNANCEMENT', 'ORDONNANCEMENT_PRESIDENT', 'PAIEMENT'])
    ->orderByRaw("array_position(array['BESOIN_PAP','BESOIN_PAP_APPUI','BESOIN_HORS_PAP','ENGAGEMENT','LIQUIDATION','ORDONNANCEMENT','ORDONNANCEMENT_PRESIDENT','PAIEMENT']::text[], w.code::text)")
    ->orderBy('e.ordre')->get(['w.code', 'w.libelle as wl', 'e.ordre', 'e.libelle', 'e.role'])->groupBy('code');
$lignesCircuits = [];
foreach ($circuits as $code => $etapes) {
    $lignesCircuits[] = [$code, $etapes->map(fn ($e) => "{$e->ordre}. {$e->libelle} ({$e->role})")->implode(' → ')];
}

$constatsTransverses = [
    ['CORRIGÉ', 'Numérotation des écritures comptables, des audits de dépense et des rapprochements', 'Numero::suivant interrogeait une colonne « numero » absente de ces tables (numero_piece / reference). SQLite tolère l\'identifiant inconnu, d\'où des tests verts ; sous MySQL comme sous PostgreSQL, la validation finale de tout paiement échouait.', 'Corrigé dans EcritureComptableService (3 appels), DepenseAuditService et RapprochementBancaireService ; test de non-régression ajouté (DepenseD4Test).'],
    ['CORRIGÉ', 'Longueur de journaux_audit.action', 'Colonne limitée à 30 caractères alors que des actions en comptent jusqu\'à 36 (engagement_insuffisance_credits, ordonnancement_alerte_fractionnement, structure_budget_ligne_*). Sous PostgreSQL, l\'erreur interrompt la transaction métier : mise en attente d\'arbitrage et alerte de fractionnement échouaient.', 'Migration 2026_10_24_240000 : colonne portée à 64 caractères.'],
    ['À NOTER', 'Activités PAP non rattachées au budget', 'Aucune des 215 activités PAP actives n\'est rattachée à une ligne budgétaire : l\'écran de création d\'EB PAP ne propose aucune activité.', 'Prérequis de démonstration : 12 activités rattachées à l\'enveloppe de leur axe (ACT-<axe>). Rattacher l\'ensemble des activités relève d\'une décision PAP / Budget.'],
    ['À NOTER', 'Point focal sans droit de soumission', 'Le point focal peut créer une EB mais pas la soumettre (permission besoin.modifier absente) : la soumission revient au chef de service.', 'Organisation retenue pour la démonstration (EB-06) ; à confirmer comme règle de gestion.'],
    ['À NOTER', 'Dossier de paiement automatique non modifiable', 'Le paiement généré à la signature de l\'ORD est en virement, sans RIB, pour la totalité, et aucune route ne permet de le compléter. Pour payer par chèque, en devise ou en plusieurs fois, le comptable doit le rejeter puis le gestionnaire saisir de nouveaux paiements.', 'Prévoir l\'édition du dossier en préparation (mode, RIB, montant) avant soumission.'],
    ['À NOTER', 'Traductions françaises absentes', 'Aucun répertoire lang/fr : les messages de validation s\'affichent sous la forme « validation.required » dans toute l\'application.', 'Ajouter lang/fr/validation.php (et auth, pagination, passwords).'],
    ['À NOTER', 'MFA obligatoire pour SG, CF et Commissaire', 'Ces comptes sont redirigés vers l\'activation de la double authentification à la première connexion.', 'Enrôler une application d\'authentification sur chaque compte avant la séance.'],
];

$anomalies = [];
foreach ($scenarios as $s) {
    foreach ($s['controles'] as $k) {
        if ($k['conforme'] !== true) {
            $anomalies[] = [$statutEtape($k['conforme']), $s['id'], $k['libelle'], $k['obtenu'], $k['attendu']];
        }
    }
    foreach ($s['etapes'] as $e) {
        if ($e['conforme'] !== true) {
            $anomalies[] = ['NON CONFORME', $s['id'], $e['action'], "HTTP {$e['http']} — {$e['message']}", $e['attendu']];
        }
    }
    if ($s['abandon']) {
        $anomalies[] = ['NON CONFORME', $s['id'], 'Scénario interrompu', $s['abandon'], 'Exécution complète'];
    }
}
$recommandations = [
    'NOM-02' => 'La règle BesoinCircuitService::estDirectionAppui remonte tous les ancêtres ; or PRES figure dans codes_structure_appui et tous les départements en descendent. Toute EB PAP suit donc le circuit « appui » (Directeur → SG) et le Commissaire n\'est jamais sollicité. Arrêter la remontée au premier département technique, ou retirer PRES des codes d\'appui, après arbitrage institutionnel.',
    'PAY-06' => 'DepenseQuotaService::assertPeutPayer compare le montant en devise au reste à payer en XAF. Comparer la contre-valeur (montant × taux) au reste à payer, et calculer les compteurs en XAF.',
    'NOM-01' => 'Permettre de compléter le dossier de paiement automatique (RIB, mode) et exiger le RIB d\'un virement avant visa.',
    'EB-01' => 'Après correction, proposer une action « Renvoyer au circuit » au demandeur, ou présenter l\'EB comme « en attente du Directeur ».',
    'LIQ-06' => 'Arbitrer : certification par un autre agent du service initiateur, ou héritage de l\'initiateur sur le gestionnaire qui saisit la liquidation.',
    'LIQ-07' => 'Exiger la saisie du service fait à l\'étape « Certification du service fait » (et non seulement au visa du CF).',
    'PAY-02' => 'Ajouter les fichiers de traduction lang/fr.',
];

// ───────────────────────── Modèle de document ─────────────────────────

$doc = [];
$h1 = function (string $t, bool $saut = false) use (&$doc) { $doc[] = ['h1', $t, $saut]; };
$h2 = function (string $t) use (&$doc) { $doc[] = ['h2', $t]; };
$p = function (string $t, bool $gras = false) use (&$doc) { $doc[] = ['p', $t, $gras]; };
$puces = function (array $items) use (&$doc) { $doc[] = ['puces', $items]; };
/** $statut : index de la colonne de statut à colorer ; $cles : 1re colonne en en-tête de ligne (fiche clé/valeur). */
$table = function (array $entetes, array $lignes, array $largeurs, ?int $statut = null, bool $cles = false, bool $compact = false) use (&$doc) {
    $doc[] = ['table', $entetes, $lignes, $largeurs, $statut, $cles, $compact];
};
$fiche = function (array $s) use (&$doc) { $doc[] = ['fiche', $s]; };

$h1('1. Objet et méthode');
$p('Ce document constitue le jeu d\'essai de la démonstration de la chaîne de la dépense de GEST-BUDGET-PAP : Expression de besoin (EB) → Engagement (ENG) → Liquidation (LIQ) → Ordonnancement (ORD) → Paiement (PAY). Il décrit '.$tot['total'].' scénarios, du parcours nominal aux cas de rejet, de contrôle et d\'exception, et consigne pour chacun le résultat effectivement obtenu.');
$p('Chaque scénario a été joué par l\'API de l\'application (celle qu\'utilise l\'interface), avec les comptes de démonstration titulaires des rôles réels, dans le respect des circuits de validation. Les dossiers produits sont conservés en base : ils constituent le support de la démonstration et sont repérables par le préfixe « [DEMO-<n° de scénario>] » dans leur objet. Au total : '.$nbEtapes.' actions et '.$nbControles.' contrôles.');
$p('Le jeu d\'essai est rejouable : php database/scripts/demo_chaine_depense.php (option --essai pour une exécution sans conservation), puis php database/scripts/demo_chaine_depense_livrable.php pour régénérer ce document.');
$table(['Élément', 'Valeur'], [
    ['Date d\'exécution', $meta['execute_le'].' (heure locale)'],
    ['Mode', $meta['mode']],
    ['Exercice budgétaire', (string) $meta['exercice']],
    ['Seuil d\'ordonnancement SG / Président', $xaf($meta['seuil']).' (paramètre versionné, égalité = SG)'],
    ['Base de données', $meta['pg']],
    ['Application', 'Laravel '.$meta['laravel'].', PHP '.$meta['php'].' — http://budget-pap-suivi-ceeac.test'],
], [3200, 5800], null, true);

$h1('2. Préparation de la démonstration');
$h2('Comptes de démonstration');
$p('Mot de passe de tous les comptes : « password ». La colonne MFA indique les comptes soumis à la double authentification obligatoire.');
$table(['Profil', 'Identifiant', 'Rôle dans la chaîne', 'MFA'], $comptes, [2300, 2900, 3100, 700], null, false, true);
$h2('Prérequis appliqués à la base');
$table(['Prérequis', 'Détail'], $data['prerequis'], [2600, 6400], null, true, true);
$h2('Circuits de validation en vigueur');
$table(['Circuit', 'Étapes (rôle)'], $lignesCircuits, [2600, 6400], null, true, true);

$h1('3. Synthèse des résultats');
$p("{$tot['total']} scénarios : {$tot['CONFORME']} conformes, {$tot['RÉSERVE']} sous réserve, {$tot['NON CONFORME']} non conformes.", true);
$lignesSyn = [];
foreach ($familles + ['TOTAL' => $tot] as $nf => $f) {
    $lignesSyn[] = [$nf, (string) $f['total'], (string) ($f['CONFORME'] ?? 0), (string) ($f['RÉSERVE'] ?? 0), (string) ($f['NON CONFORME'] ?? 0)];
}
$table(['Famille', 'Scénarios', 'Conformes', 'Réserves', 'Non conformes'], $lignesSyn, [3400, 1400, 1400, 1400, 1400]);
$p('Lecture : « conforme » — le comportement obtenu correspond à la procédure ; « réserve » — résultat obtenu mais point de procédure ou d\'ergonomie à arbitrer ; « non conforme » — écart avec la règle attendue (section 6).');

$h2('Matrice des scénarios');
$lignesMat = [];
foreach ($scenarios as $s) {
    $final = collect($pieces($s))->last();
    $lignesMat[] = [$s['id'], $s['titre'], $final ? "{$final[1]} ({$final[2]})" : '—', $s['statut']];
}
$table(['N°', 'Scénario', 'Dernière pièce produite', 'Statut'], $lignesMat, [900, 4700, 2100, 1300], 3, false, true);

$h1('4. Guide de démonstration', true);
$p('Déroulé conseillé (environ 60 minutes). Les numéros de pièces sont ceux créés lors de l\'exécution ; ouvrir le dossier unifié depuis l\'ENG pour montrer la chronologie complète.');
$guide = [
    ['1. Parcours nominal', "NOM-01 — {$num('NOM-01', 'EB')} → {$num('NOM-01', 'ENG')} → {$num('NOM-01', 'LIQ')} → {$num('NOM-01', 'ORD')} → {$num('NOM-01', 'PAY')}", 'gestionnaire_budgetaire', 'Total calculé depuis les articles, génération automatique de chaque pièce, ordre de paiement, écriture comptable, chaîne clôturée.'],
    ['2. Les trois circuits d\'EB', "NOM-01 (appui), NOM-02 ({$num('NOM-02', 'EB')}), NOM-03 hors PAP ({$num('NOM-03', 'EB')})", 'chef_service puis directeur / secretaire_general / president', 'Circuits Directeur → SG et DRHMG → SG → Président ; héritage PAP en lecture seule ; justification hors PAP.'],
    ['3. Compétence de l\'ordonnateur', "NOM-05 ({$num('NOM-05', 'ORD')}, 5 000 000 → SG) et NOM-04 ({$num('NOM-04', 'ORD')}, 12 500 000 → Président) ; NOM-06 délégation ({$num('NOM-06', 'ORD')})", 'secretaire_general, president, directeur.plan', 'Seuil versionné et non rétroactif, égalité au seuil, délégation écrite.'],
    ['4. Corbeilles et dossiers en cours', "EB-05 ({$num('EB-05', 'EB')}, attente Directeur), ENG-04 ({$num('ENG-04', 'ENG')}, attente CF), ENG-01 ({$num('ENG-01', 'ENG')}, attente d\'arbitrage)", 'directeur, controleur_financier', 'Dossiers à traiter par acteur ; insuffisance de crédits tracée sans crédit créé.'],
    ['5. Rejets et retours', "EB-01 renvoi ({$num('EB-01', 'EB')}), EB-02, ENG-02, ENG-03, LIQ-05, ORD-01", 'directeur, secretaire_general, controleur_financier', 'Rejets motivés et catégorisés ; libération des crédits ; aucune pièce aval générée.'],
    ['6. Liquidation', "LIQ-01 tranches ({$num('LIQ-01', 'LIQ_T1')}, {$num('LIQ-01', 'LIQ_T2')}), LIQ-03 brut/net ({$num('LIQ-03', 'LIQ')}), LIQ-04 doublon de facture, ENG-05 dégagement", 'gestionnaire_budgetaire, chef_service, controleur_financier', 'Service fait, pièces, tranches, retenues, alerte de doublon, dégagement plafonné.'],
    ['7. Paiement et trésorerie', "PAY-01 ({$num('PAY-01', 'PAY_1')} puis {$num('PAY-01', 'PAY_2')}), PAY-05 chèque rejeté ({$num('PAY-05', 'PAY_CHEQUE')})", 'comptable, agent_comptable', 'Paiement échelonné motivé, double visa comptable, contrepassation sans effacement, réouverture de la chaîne.'],
    ['8. Contrôle interne', 'LIQ-06, PAY-03, PAY-04 (séparation des fonctions), ORD-02 (fractionnement), ORD-03 (compétence), EB-04 / PAY-02 (contrôles de saisie)', 'auditeur pour la piste d\'audit', 'Refus motivés, alertes d\'audit non bloquantes, journal d\'audit.'],
];
$table(['Séquence', 'Scénarios et pièces', 'Compte(s)', 'Points à montrer'], $guide, [1700, 3300, 1800, 2200], null, false, true);

$h1('5. Fiches de scénarios', true);
$famille = null;
foreach ($scenarios as $s) {
    if ($s['famille'] !== $famille) {
        $famille = $s['famille'];
        $h2($famille);
    }
    $fiche($s);
}

$h1('6. Constats, anomalies et recommandations', true);
$h2('Écarts relevés par les scénarios');
$table(['Statut', 'Scénario', 'Point de contrôle', 'Obtenu', 'Attendu'], $anomalies, [1300, 900, 2000, 3200, 1600], 0, false, true);
$h2('Recommandations');
$lignesReco = [];
foreach ($recommandations as $id => $r) {
    if (isset($sc[$id]) && $sc[$id]['statut'] !== 'CONFORME') {
        $lignesReco[] = [$id, $sc[$id]['statut'], $r];
    }
}
$table(['Scénario', 'Statut', 'Analyse et recommandation'], $lignesReco, [1000, 1400, 6600], 1, false, true);
$h2('Constats transverses de la préparation');
$table(['Nature', 'Sujet', 'Constat', 'Suite donnée'], $constatsTransverses, [1200, 1900, 3600, 2300], 0, false, true);

$h1('7. Contrôles globaux');
$p('Vérifications sur l\'ensemble des dossiers de démonstration après exécution.');
$table(['Contrôle', 'Statut', 'Détail'], array_map(fn ($g) => [$g[0], $g[1] ? 'CONFORME' : 'NON CONFORME', $g[2]], $data['globaux']), [3000, 1300, 4700], 1, false, true);

$h1('8. Conclusion et visa');
$p("Sur {$tot['total']} scénarios, {$tot['CONFORME']} sont conformes et {$tot['RÉSERVE']} relèvent de points d'organisation ou d'ergonomie à arbitrer. Les {$tot['NON CONFORME']} non-conformités (".collect($scenarios)->where('statut', 'NON CONFORME')->pluck('id')->implode(', ').') sont documentées en section 6 avec leur cause et la correction proposée. La chaîne EB → PAY est démontrable de bout en bout : génération automatique des pièces, circuits de visa, contrôles budgétaires et comptables, séparation des fonctions et piste d\'audit. Deux défauts bloquants découverts lors de la préparation ont été corrigés (numérotation des écritures comptables, longueur des actions d\'audit).');
$table(['Rédigé par', 'Vérifié par', 'Approuvé par'], [["Nom :\nDate :\nSignature :", "Nom :\nDate :\nSignature :", "Nom :\nDate :\nSignature :"]], [3000, 3000, 3000], null, false, false);

// ───────────────────────── Rendu DOCX ─────────────────────────

$w = new PhpWord;
$w->setDefaultFontName('Calibri');
$w->setDefaultFontSize(10);
$w->getDocInfo()->setTitle('Jeu d\'essai — Démonstration de la chaîne de la dépense')->setCompany('Commission de la CEEAC')->setCreator('DSI CEEAC');
$w->addTitleStyle(1, ['size' => 15, 'bold' => true, 'color' => BLEU], ['spaceBefore' => 240, 'spaceAfter' => 120]);
$w->addTitleStyle(2, ['size' => 12, 'bold' => true, 'color' => BLEU_FONCE], ['spaceBefore' => 200, 'spaceAfter' => 80, 'keepNext' => true]);
$w->addTableStyle('grille', ['borderSize' => 4, 'borderColor' => 'B8C4D6', 'cellMargin' => 60]);
$p0 = ['spaceAfter' => 0];
$marges = ['marginLeft' => Converter::cmToTwip(1.8), 'marginRight' => Converter::cmToTwip(1.8), 'marginTop' => Converter::cmToTwip(1.8), 'marginBottom' => Converter::cmToTwip(1.6)];
$texte = function ($cell, string $t, array $style = [], array $para = []) use ($p0) {
    foreach (explode("\n", $t) as $i => $l) {
        $cell->addText($l, $style, $p0 + $para);
    }
};
$docxTable = function ($s, array $entetes, array $lignes, array $larg, ?int $statut, bool $cles, bool $compact) use ($texte) {
    $taille = $compact ? 8 : 9;
    $t = $s->addTable('grille');
    if ($entetes) {
        $t->addRow(null, ['tblHeader' => true]);
        foreach ($entetes as $i => $h) {
            $texte($t->addCell($larg[$i], ['bgColor' => BLEU]), $h, ['bold' => true, 'color' => 'FFFFFF', 'size' => $taille]);
        }
    }
    foreach ($lignes as $l) {
        $t->addRow(null, ['cantSplit' => true]);
        foreach (array_values($l) as $i => $v) {
            $v = (string) $v;
            if ($statut === $i && isset(COULEURS[$v])) {
                $texte($t->addCell($larg[$i], ['bgColor' => COULEURS[$v][1]]), $v, ['bold' => true, 'size' => $taille - 1, 'color' => COULEURS[$v][0]]);
            } elseif ($cles && $i === 0) {
                $texte($t->addCell($larg[$i], ['bgColor' => 'EEF3FA']), $v, ['bold' => true, 'size' => $taille]);
            } else {
                $texte($t->addCell($larg[$i]), $v, ['size' => $taille]);
            }
        }
    }
};

$s = $w->addSection($marges);
$s->addTextBreak(3);
if (is_file($logo)) {
    $s->addImage($logo, ['width' => 90, 'alignment' => Jc::CENTER]);
}
$s->addTextBreak(1);
$s->addText('COMMISSION DE LA CEEAC', ['size' => 12, 'bold' => true, 'color' => BLEU_FONCE], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
$s->addText('Direction des Systèmes d\'Information', ['size' => 10, 'color' => '555555'], ['alignment' => Jc::CENTER]);
$s->addTextBreak(3);
$s->addText('JEU D\'ESSAI DE DÉMONSTRATION', ['size' => 26, 'bold' => true, 'color' => BLEU], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
$s->addText('Chaîne de la dépense : EB → ENG → LIQ → ORD → PAY', ['size' => 15, 'color' => BLEU_FONCE], ['alignment' => Jc::CENTER]);
$s->addText('Application GEST-BUDGET-PAP', ['size' => 12, 'color' => '555555'], ['alignment' => Jc::CENTER]);
$s->addTextBreak(3);
$g = $s->addTable(['borderSize' => 4, 'borderColor' => 'B8C4D6', 'cellMargin' => 90, 'alignment' => Jc::CENTER]);
foreach ([['Version', '1.0'], ['Date d\'exécution', $meta['execute_le']], ['Scénarios', (string) $tot['total']], ['Résultat', "{$tot['CONFORME']} conformes · {$tot['RÉSERVE']} réserves · {$tot['NON CONFORME']} non conformes"], ['Statut du document', 'Pour validation']] as [$k, $v]) {
    $g->addRow();
    $g->addCell(3000, ['bgColor' => 'EEF3FA'])->addText($k, ['bold' => true], $p0);
    $g->addCell(4600)->addText($v, [], $p0);
}

$s = $w->addSection($marges);
$s->addFooter()->addPreserveText('Jeu d\'essai de démonstration — Chaîne de la dépense · GEST-BUDGET-PAP · Page {PAGE} / {NUMPAGES}', ['size' => 8, 'color' => '777777'], ['alignment' => Jc::CENTER]);
foreach ($doc as $b) {
    switch ($b[0]) {
        case 'h1':
            if ($b[2]) {
                $s->addPageBreak();
            }
            $s->addTitle($b[1], 1);
            break;
        case 'h2':
            $s->addTitle($b[1], 2);
            break;
        case 'p':
            $s->addText($b[1], $b[2] ? ['bold' => true] : [], ['alignment' => Jc::BOTH]);
            break;
        case 'puces':
            foreach ($b[1] as $it) {
                $s->addListItem($it, 0);
            }
            break;
        case 'table':
            $docxTable($s, $b[1], $b[2], $b[3], $b[4], $b[5], $b[6]);
            $s->addTextBreak(1, ['size' => 4]);
            break;
        case 'fiche':
            $f = $b[1];
            $t = $s->addTable('grille');
            $t->addRow(null, ['cantSplit' => true]);
            $texte($t->addCell(7300, ['bgColor' => BLEU]), "{$f['id']} — {$f['titre']}", ['bold' => true, 'color' => 'FFFFFF', 'size' => 10]);
            $texte($t->addCell(2100, ['bgColor' => COULEURS[$f['statut']][1]]), $f['statut'], ['bold' => true, 'color' => COULEURS[$f['statut']][0]], ['alignment' => Jc::CENTER]);
            foreach ([['Objectif', $f['objectif']], ['Acteurs', $f['acteurs']], ['Données d\'entrée', $f['donnees']], ['Résultat attendu', $f['attendu']]] as [$k, $v]) {
                $t->addRow(null, ['cantSplit' => true]);
                $texte($t->addCell(2100, ['bgColor' => 'EEF3FA']), $k, ['bold' => true, 'size' => 9]);
                $texte($t->addCell(7300), $v, ['size' => 9]);
            }
            if ($f['abandon']) {
                $t->addRow();
                $texte($t->addCell(2100, ['bgColor' => COULEURS['NON CONFORME'][1]]), 'Interruption', ['bold' => true, 'size' => 9]);
                $texte($t->addCell(7300), $f['abandon'], ['size' => 9]);
            }
            $pc = $pieces($f);
            if ($pc) {
                $s->addText('Pièces produites', ['bold' => true, 'size' => 9, 'color' => BLEU_FONCE], ['spaceBefore' => 80, 'spaceAfter' => 20]);
                $docxTable($s, ['Pièce', 'Numéro', 'Statut final', 'Montant'], $pc, [2400, 2400, 2500, 2100], null, false, true);
            }
            $s->addText('Déroulé', ['bold' => true, 'size' => 9, 'color' => BLEU_FONCE], ['spaceBefore' => 80, 'spaceAfter' => 20]);
            $docxTable($s, ['N°', 'Action', 'Acteur', 'Attendu', 'HTTP / obtenu', 'Statut'], array_map(fn ($e) => [
                (string) $e['n'], $e['action'], $e['acteur'], $e['attendu'], $e['http'].($e['message'] ? ' — '.$e['message'] : '').($e['obtenu'] ? ' ['.$e['obtenu'].']' : ''), $statutEtape($e['conforme']),
            ], $f['etapes']), [400, 2000, 1800, 1700, 2500, 1000], 5, false, true);
            if ($f['controles']) {
                $s->addText('Contrôles', ['bold' => true, 'size' => 9, 'color' => BLEU_FONCE], ['spaceBefore' => 80, 'spaceAfter' => 20]);
                $docxTable($s, ['Point de contrôle', 'Attendu', 'Obtenu', 'Statut'], array_map(fn ($k) => [$k['libelle'], $k['attendu'], $k['obtenu'], $statutEtape($k['conforme'])], $f['controles']), [2300, 2100, 4000, 1000], 3, false, true);
            }
            $s->addTextBreak(1);
            break;
    }
}
$w->save("{$sortie}/{$nom}.docx", 'Word2007');

// ───────────────────────── Rendu PDF ─────────────────────────

$e = fn ($v) => nl2br(htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'));
$badge = fn (string $st) => isset(COULEURS[$st]) ? '<span class="b" style="color:#'.COULEURS[$st][0].';background:#'.COULEURS[$st][1].'">'.$e($st).'</span>' : $e($st);
$htmlTable = function (array $entetes, array $lignes, array $larg, ?int $statut, bool $cles, bool $compact) use ($e, $badge) {
    $total = array_sum($larg);
    $h = '<table class="'.($compact ? 'compact' : '').'">';
    if ($entetes) {
        $h .= '<thead><tr>';
        foreach ($entetes as $i => $t) {
            $h .= '<th style="width:'.round($larg[$i] / $total * 100, 1).'%">'.$e($t).'</th>';
        }
        $h .= '</tr></thead>';
    }
    foreach ($lignes as $l) {
        $h .= '<tr>';
        foreach (array_values($l) as $i => $v) {
            $h .= $statut === $i ? '<td>'.$badge((string) $v).'</td>' : '<td'.($cles && $i === 0 ? ' class="k"' : '').'>'.$e($v).'</td>';
        }
        $h .= '</tr>';
    }

    return $h.'</table>';
};
$logoData = is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : '';
$corps = '';
foreach ($doc as $b) {
    $corps .= match ($b[0]) {
        'h1' => '<h1'.($b[2] ? ' class="saut"' : '').'>'.$e($b[1]).'</h1>',
        'h2' => '<h2>'.$e($b[1]).'</h2>',
        'p' => '<p>'.($b[2] ? '<b>'.$e($b[1]).'</b>' : $e($b[1])).'</p>',
        'puces' => '<ul>'.implode('', array_map(fn ($i) => '<li>'.$e($i).'</li>', $b[1])).'</ul>',
        'table' => $htmlTable($b[1], $b[2], $b[3], $b[4], $b[5], $b[6]),
        'fiche' => (function ($f) use ($e, $badge, $htmlTable, $pieces, $statutEtape) {
            $h = '<div class="fiche"><table><tr><th class="titre">'.$e("{$f['id']} — {$f['titre']}").'</th><th style="width:18%;text-align:center;background:#'.COULEURS[$f['statut']][1].';color:#'.COULEURS[$f['statut']][0].'">'.$e($f['statut']).'</th></tr>';
            foreach ([['Objectif', $f['objectif']], ['Acteurs', $f['acteurs']], ['Données d\'entrée', $f['donnees']], ['Résultat attendu', $f['attendu']]] as [$k, $v]) {
                $h .= '<tr><td class="k">'.$e($k).'</td><td>'.$e($v).'</td></tr>';
            }
            if ($f['abandon']) {
                $h .= '<tr><td class="k">Interruption</td><td>'.$e($f['abandon']).'</td></tr>';
            }
            $h .= '</table></div>';
            if ($pc = $pieces($f)) {
                $h .= '<div class="sous">Pièces produites</div>'.$htmlTable(['Pièce', 'Numéro', 'Statut final', 'Montant'], $pc, [2400, 2400, 2500, 2100], null, false, true);
            }
            $h .= '<div class="sous">Déroulé</div>'.$htmlTable(['N°', 'Action', 'Acteur', 'Attendu', 'HTTP / obtenu', 'Statut'], array_map(fn ($x) => [
                (string) $x['n'], $x['action'], $x['acteur'], $x['attendu'], $x['http'].($x['message'] ? ' — '.$x['message'] : '').($x['obtenu'] ? ' ['.$x['obtenu'].']' : ''), $statutEtape($x['conforme']),
            ], $f['etapes']), [400, 2000, 1800, 1700, 2500, 1100], 5, false, true);
            if ($f['controles']) {
                $h .= '<div class="sous">Contrôles</div>'.$htmlTable(['Point de contrôle', 'Attendu', 'Obtenu', 'Statut'], array_map(fn ($k) => [$k['libelle'], $k['attendu'], $k['obtenu'], $statutEtape($k['conforme'])], $f['controles']), [2300, 2100, 4000, 1100], 3, false, true);
            }

            return $h.'<div class="sep"></div>';
        })($b[1]),
    };
}
$garde = '';
foreach ([['Version', '1.0'], ['Date d\'exécution', $meta['execute_le']], ['Scénarios', $tot['total']], ['Résultat', "{$tot['CONFORME']} conformes · {$tot['RÉSERVE']} réserves · {$tot['NON CONFORME']} non conformes"], ['Statut du document', 'Pour validation']] as [$k, $v]) {
    $garde .= '<tr><td class="k">'.$e($k).'</td><td>'.$e($v).'</td></tr>';
}
$html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
@page { margin: 1.7cm 1.5cm 1.6cm 1.5cm; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #1f2933; line-height: 1.35; }
h1 { color: #'.BLEU.'; font-size: 14pt; border-bottom: 2px solid #'.BLEU.'; padding-bottom: 3px; margin: 16px 0 8px; }
h1.saut { page-break-before: always; }
h2 { color: #'.BLEU_FONCE.'; font-size: 11pt; margin: 12px 0 6px; page-break-after: avoid; }
p { margin: 0 0 6px; text-align: justify; }
table { width: 100%; border-collapse: collapse; margin: 3px 0 8px; }
th { background: #'.BLEU.'; color: #fff; text-align: left; font-size: 7.5pt; padding: 3px 4px; }
td { border: 1px solid #B8C4D6; padding: 3px 4px; vertical-align: top; font-size: 7.8pt; }
table.compact td { font-size: 7pt; padding: 2px 3px; }
table.compact th { font-size: 7pt; }
td.k { background: #EEF3FA; font-weight: bold; width: 24%; }
tr { page-break-inside: avoid; }
.b { font-weight: bold; font-size: 6.8pt; padding: 1px 3px; white-space: nowrap; }
.fiche th.titre { font-size: 9pt; }
.sous { color: #'.BLEU_FONCE.'; font-weight: bold; font-size: 8pt; margin: 4px 0 1px; }
.sep { border-bottom: 1px dashed #B8C4D6; margin: 6px 0 12px; }
.garde { text-align: center; padding-top: 60px; page-break-after: always; }
.garde .titre { color: #'.BLEU.'; font-size: 24pt; font-weight: bold; margin-top: 60px; }
.garde table { width: 62%; margin: 60px auto 0; }
.garde td { font-size: 9pt; text-align: left; }
footer { position: fixed; bottom: -1.1cm; left: 0; right: 0; text-align: center; font-size: 6.5pt; color: #777; }
</style></head><body>
<footer>Jeu d\'essai de démonstration — Chaîne de la dépense · GEST-BUDGET-PAP · Commission de la CEEAC</footer>
<div class="garde">'.($logoData ? '<img src="'.$logoData.'" style="width:105px">' : '').'
<div style="color:#'.BLEU_FONCE.';font-weight:bold;font-size:12pt;margin-top:12px">COMMISSION DE LA CEEAC</div>
<div style="color:#555">Direction des Systèmes d\'Information</div>
<div class="titre">JEU D\'ESSAI DE DÉMONSTRATION</div>
<div style="color:#'.BLEU_FONCE.';font-size:14pt">Chaîne de la dépense : EB → ENG → LIQ → ORD → PAY</div>
<div style="color:#555;font-size:11pt">Application GEST-BUDGET-PAP</div>
<table>'.$garde.'</table></div>'.$corps.'</body></html>';

$opt = new Options;
$opt->set('isRemoteEnabled', false);
$opt->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf($opt);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4');
$pdf->render();
$pdf->getCanvas()->page_text(525, 818, 'Page {PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 6.5, [0.47, 0.47, 0.47]);
file_put_contents("{$sortie}/{$nom}.pdf", $pdf->output());

echo "{$sortie}/{$nom}.docx".PHP_EOL."{$sortie}/{$nom}.pdf".PHP_EOL;
