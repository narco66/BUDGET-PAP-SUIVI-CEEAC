<?php

/**
 * Contrôle d'unicité des référentiels et des dossiers (lecture seule).
 * Pour chaque clé métier : doublons réels (enregistrements actifs, non supprimés) et présence d'un index unique.
 *
 * Usage : php database/scripts/audit_unicite_referentiels.php <sortie.json>
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// [table, colonnes de la clé métier, filtre SQL (périmètre où la clé doit être unique), libellé]
$cles = [
    ['exercices', ['annee'], null, 'Exercice : année'],
    ['exercices', ['code'], 'code IS NOT NULL', 'Exercice : code'],
    ['budget_titres', ['code'], 'deleted_at IS NULL', 'Nomenclature : titre'],
    ['budget_articles', ['titre_id', 'code'], 'deleted_at IS NULL', 'Nomenclature : article dans son titre'],
    ['budget_paragraphes', ['article_id', 'code'], 'deleted_at IS NULL', 'Nomenclature : paragraphe dans son article'],
    ['budget_rubriques', ['paragraphe_id', 'code'], 'deleted_at IS NULL', 'Nomenclature : rubrique dans son paragraphe'],
    ['budget_rubriques', ['code'], 'deleted_at IS NULL', 'Nomenclature : code de rubrique global'],
    ['lignes_budgetaires', ['exercice_id', 'code'], 'deleted_at IS NULL', 'Ligne budgétaire : exercice + code'],
    ['lignes_budgetaires_details', ['exercice_id', 'code'], null, 'Sous-ligne de détail : exercice + code'],
    ['structures', ['code'], 'deleted_at IS NULL', 'Unité organique : code'],
    ['structures', ['sigle'], 'deleted_at IS NULL AND sigle IS NOT NULL', 'Unité organique : sigle'],
    ['users', ['email'], null, 'Utilisateur : identifiant de connexion (e-mail)'],
    ['users', ['matricule'], 'matricule IS NOT NULL', 'Utilisateur : matricule'],
    ['piliers', ['exercice_id', 'code'], 'deleted_at IS NULL', 'PAP : pilier'],
    ['axes', ['pilier_id', 'code'], 'deleted_at IS NULL', 'PAP : axe dans son pilier'],
    ['produits', ['axe_id', 'code'], 'deleted_at IS NULL', 'PAP : produit dans son axe'],
    ['sous_produits', ['produit_id', 'code'], 'deleted_at IS NULL', 'PAP : sous-produit dans son produit'],
    ['activites', ['code'], 'deleted_at IS NULL', 'PAP : code d\'activité'],
    ['taches', ['activite_id', 'code'], 'deleted_at IS NULL', 'PAP : tâche dans son activité'],
    ['sources_financement', ['code'], null, 'Source de financement : code'],
    ['expressions_besoin', ['numero'], null, 'EB : référence'],
    ['engagements', ['numero'], null, 'ENG : référence'],
    ['engagements', ['expression_besoin_id'], 'expression_besoin_id IS NOT NULL', 'ENG : un engagement par EB'],
    ['liquidations', ['numero'], null, 'LIQ : référence'],
    ['ordonnancements', ['numero'], null, 'ORD : référence'],
    ['ordonnancements', ['liquidation_id'], "statut NOT IN ('rejete','annule')", 'ORD : un ordonnancement actif par liquidation'],
    ['paiements', ['numero'], null, 'PAY : référence'],
    ['ordres_paiement', ['numero'], null, 'Ordre de paiement : référence'],
    ['engagement_imputations', ['engagement_id', 'ligne_budgetaire_id'], null, 'Imputation : ligne unique par engagement'],
    ['reservations_credits', ['engagement_id', 'ligne_budgetaire_id'], "statut = 'active'", 'Réservation active unique par engagement et ligne'],
    ['ecritures_comptables', ['numero_piece'], 'numero_piece IS NOT NULL', 'Écriture : numéro de pièce'],
    ['ged_documents', ['reference'], null, 'GED : référence'],
    ['workflow_taches', ['reference'], null, 'Tâche workflow : référence'],
];

$index = [];
foreach (DB::select("SELECT tablename, indexdef FROM pg_indexes WHERE schemaname = current_schema() AND indexdef ILIKE '%UNIQUE%'") as $i) {
    $index[$i->tablename][] = $i->indexdef;
}

$resultats = [];
foreach ($cles as [$table, $colonnes, $filtre, $libelle]) {
    if (! Schema::hasTable($table) || array_diff($colonnes, Schema::getColumnListing($table))) {
        $resultats[] = ['cle' => $libelle, 'table' => $table, 'statut' => 'non applicable (colonne absente)'];
        continue;
    }
    $liste = implode(', ', $colonnes);
    $where = $filtre ? "WHERE {$filtre}" : '';
    $doublons = DB::select("SELECT {$liste}, COUNT(*) AS n, STRING_AGG(id::text, ',' ORDER BY id) AS ids FROM {$table} {$where} GROUP BY {$liste} HAVING COUNT(*) > 1");
    $total = DB::selectOne("SELECT COUNT(*) AS n FROM {$table} {$where}")->n;
    $indexe = collect($index[$table] ?? [])->contains(function ($def) use ($colonnes) {
        preg_match('/\(([^)]+)\)/', $def, $m);
        $cols = array_map(fn ($c) => trim($c, ' "'), explode(',', $m[1] ?? ''));

        return $cols === $colonnes;
    });
    $resultats[] = [
        'cle' => $libelle,
        'table' => $table,
        'colonnes' => $colonnes,
        'perimetre' => $filtre,
        'enregistrements' => (int) $total,
        'groupes_en_double' => count($doublons),
        'doublons' => array_map(fn ($d) => (array) $d, $doublons),
        'index_unique' => $indexe,
    ];
}

file_put_contents($argv[1] ?? 'php://stdout', json_encode($resultats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
foreach ($resultats as $r) {
    echo str_pad($r['cle'], 52), str_pad((string) ($r['enregistrements'] ?? '-'), 6, ' ', STR_PAD_LEFT),
        '  doublons=', str_pad((string) ($r['groupes_en_double'] ?? '-'), 3),
        '  index_unique=', isset($r['index_unique']) ? ($r['index_unique'] ? 'oui' : 'NON') : '-', PHP_EOL;
}
