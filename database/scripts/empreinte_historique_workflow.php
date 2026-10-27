<?php

/**
 * Empreinte de l'historique du workflow (décisions d'étape, visas, audit, tâches) : valeurs de décision seulement,
 * pour prouver qu'une intervention ne réécrit aucune décision passée.
 *
 * Usage : php database/scripts/empreinte_historique_workflow.php <sortie.json> [reference.json]
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$categories = [
    'circuits' => ['workflows', ['id', 'code', 'actif'], 'id'],
    'etapes_circuits' => ['workflow_etapes', ['id', 'workflow_id', 'ordre', 'role'], 'id'],
    'instances' => ['workflow_instances', ['id', 'workflow_id', 'objet_type', 'objet_id', 'statut', 'demarre_par'], 'id'],
    'decisions_etapes' => ['workflow_instance_etapes', ['id', 'instance_id', 'ordre', 'role', 'statut', 'acteur_id', 'decision_le', 'observation'], 'id'],
    'visas' => ['visas_electroniques', ['id', 'workflow_instance_id', 'type_visa', 'acteur_id', 'hash_contenu'], 'id'],
    'audit_workflow' => ['audit_workflow', ['id'], 'id'],
    'taches' => ['workflow_taches', ['id', 'reference', 'statut', 'decision', 'termine_par'], 'id'],
];

$resultat = [];
foreach ($categories as $nom => [$table, $cols, $ordre]) {
    $h = hash_init('sha256');
    $n = 0;
    foreach (DB::table($table)->select($cols)->orderBy($ordre)->cursor() as $row) {
        hash_update($h, implode("\x1f", array_map(fn ($c) => (string) ($row->$c ?? '∅'), $cols))."\x1e");
        $n++;
    }
    $resultat[$nom] = ['lignes' => $n, 'sha256' => hash_final($h)];
}
file_put_contents($argv[1], json_encode(['calcule_le' => now()->toIso8601String(), 'categories' => $resultat], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
foreach ($resultat as $nom => $r) {
    echo str_pad($nom, 20).str_pad($r['lignes'], 6, ' ', STR_PAD_LEFT).'  '.substr($r['sha256'], 0, 16).'…'.PHP_EOL;
}
if (isset($argv[2]) && is_file($argv[2])) {
    $ref = json_decode(file_get_contents($argv[2]), true)['categories'];
    $ecarts = array_keys(array_filter($resultat, fn ($r, $nom) => ($ref[$nom]['sha256'] ?? null) !== $r['sha256'], ARRAY_FILTER_USE_BOTH));
    echo $ecarts ? 'ÉCARTS : '.implode(', ', $ecarts).PHP_EOL : 'Aucun écart avec la référence.'.PHP_EOL;
}
