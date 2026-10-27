<?php

/**
 * Empreintes déterministes des données protégées (utilisateurs, habilitations, organigramme,
 * nomenclature, Budget 2026, dossiers EB→PAY, GED, journal). Les mots de passe chiffrés entrent dans l'empreinte mais ne sont
 * jamais affichés.
 *
 * Usage : php database/scripts/empreintes_donnees_protegees.php <sortie.json> [reference.json]
 *   Avec une référence : compare et liste les catégories modifiées (code retour 1 si écart).
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$categories = [
    'utilisateurs' => ['users', ['id', 'email', 'password', 'actif', 'statut', 'structure_id', 'matricule', 'deleted_at'], 'id'],
    'roles' => ['roles', ['id', 'name', 'guard_name'], 'id'],
    'permissions' => ['permissions', ['id', 'name', 'guard_name'], 'id'],
    'roles_utilisateurs' => ['model_has_roles', ['role_id', 'model_type', 'model_id'], ['model_id', 'role_id']],
    'permissions_roles' => ['role_has_permissions', ['permission_id', 'role_id'], ['role_id', 'permission_id']],
    'permissions_utilisateurs' => ['model_has_permissions', ['permission_id', 'model_type', 'model_id'], ['model_id', 'permission_id']],
    'affectations_structures' => ['user_structures', ['user_id', 'structure_id', 'principal'], ['user_id', 'structure_id']],
    'organigramme' => ['structures', ['id', 'code', 'libelle', 'type', 'parent_id', 'actif', 'deleted_at'], 'id'],
    'nomenclature_titres' => ['budget_titres', ['id', 'code', 'intitule', 'sens'], 'id'],
    'nomenclature_articles' => ['budget_articles', ['id', 'titre_id', 'code', 'intitule'], 'id'],
    'nomenclature_paragraphes' => ['budget_paragraphes', ['id', 'article_id', 'code', 'intitule'], 'id'],
    'nomenclature_rubriques' => ['budget_rubriques', ['id', 'paragraphe_id', 'code', 'intitule'], 'id'],
    'budget_2026_lignes' => ['lignes_budgetaires', ['id', 'exercice_id', 'rubrique_id', 'code', 'montant_ouvert', 'montant_revise', 'structure_id'], 'id'],
    'exercices' => ['exercices', ['id', 'annee', 'statut'], 'id'],
    // Dossiers de la chaîne de dépense, GED et journal (sans updated_at : seules les valeurs métier comptent).
    'depense_eb' => ['expressions_besoin', ['id', 'numero', 'montant_estime', 'statut'], 'id'],
    'depense_eng' => ['engagements', ['id', 'numero', 'montant_total', 'statut'], 'id'],
    'depense_liq' => ['liquidations', ['id', 'numero', 'montant', 'montant_net', 'statut'], 'id'],
    'depense_ord' => ['ordonnancements', ['id', 'numero', 'montant', 'statut'], 'id'],
    'depense_pay' => ['paiements', ['id', 'numero', 'montant', 'statut'], 'id'],
    'ged_documents' => ['ged_documents', ['id', 'reference', 'hash_sha256', 'statut'], 'id'],
    'journal_audit' => ['journaux_audit', ['id', 'empreinte'], 'id'],
];

$norm = fn ($v) => $v === null ? '∅' : (is_bool($v) ? ($v ? '1' : '0') : (is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') : (string) $v));
$resultat = [];
foreach ($categories as $nom => [$table, $cols, $ordre]) {
    $q = DB::table($table)->select($cols);
    foreach ((array) $ordre as $o) {
        $q->orderBy($o);
    }
    $h = hash_init('sha256');
    $n = 0;
    foreach ($q->cursor() as $row) {
        hash_update($h, implode("\x1f", array_map(fn ($c) => $norm($row->$c), $cols))."\x1e");
        $n++;
    }
    $resultat[$nom] = ['lignes' => $n, 'sha256' => hash_final($h)];
}

$sortie = ['calcule_le' => now('Africa/Lagos')->format('Y-m-d H:i:s'), 'categories' => $resultat];
file_put_contents($argv[1], json_encode($sortie, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$ecarts = [];
if (isset($argv[2]) && is_file($argv[2])) {
    $ref = json_decode(file_get_contents($argv[2]), true)['categories'];
    foreach ($resultat as $nom => $r) {
        if (($ref[$nom]['sha256'] ?? null) !== $r['sha256']) {
            $ecarts[] = "{$nom} (lignes {$ref[$nom]['lignes']} → {$r['lignes']})";
        }
    }
}
foreach ($resultat as $nom => $r) {
    echo str_pad($nom, 28).str_pad($r['lignes'], 6, ' ', STR_PAD_LEFT).'  '.substr($r['sha256'], 0, 16).'…'.PHP_EOL;
}
if (isset($argv[2])) {
    echo $ecarts ? 'ÉCARTS : '.implode(', ', $ecarts).PHP_EOL : 'Aucun écart avec la référence.'.PHP_EOL;
    exit($ecarts ? 1 : 0);
}
