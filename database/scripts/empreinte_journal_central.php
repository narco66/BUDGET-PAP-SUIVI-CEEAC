<?php
// Empreinte du contenu historique de journaux_audit (colonnes d'origine), pour prouver qu'aucune ligne existante
// n'a été modifiée ni supprimée par l'intervention. Lecture seule. Usage : php empreinte_journal_central.php [id_max]
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$max = isset($argv[1]) ? (int) $argv[1] : PHP_INT_MAX;
$cols = ['id', 'user_id', 'user_nom', 'role', 'auditable_type', 'auditable_id', 'action', 'avant', 'apres', 'ip', 'url', 'user_agent', 'created_at'];
$h = hash_init('sha256');
$n = 0;
foreach (Illuminate\Support\Facades\DB::table('journaux_audit')->select($cols)->where('id', '<=', $max)->orderBy('id')->cursor() as $l) {
    hash_update($h, json_encode(array_map(fn ($c) => $l->$c, $cols), JSON_UNESCAPED_UNICODE)."\n");
    $n++;
}
echo json_encode(['lignes' => $n, 'id_max' => $max === PHP_INT_MAX ? null : $max, 'sha256' => hash_final($h)]), PHP_EOL;
