<?php

/**
 * Copie les données MySQL vers PostgreSQL (schéma déjà migré par Laravel).
 * Ne détruit pas la source MySQL.
 */

$mysql = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('MYSQL_SOURCE_HOST') ?: '127.0.0.1',
        getenv('MYSQL_SOURCE_PORT') ?: '3306',
        getenv('MYSQL_SOURCE_DATABASE') ?: 'gest_budget_pap'
    ),
    getenv('MYSQL_SOURCE_USERNAME') ?: 'root',
    getenv('MYSQL_SOURCE_PASSWORD') !== false ? (string) getenv('MYSQL_SOURCE_PASSWORD') : '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pgsql = new PDO(
    sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        getenv('PG_HOST') ?: '127.0.0.1',
        getenv('PG_PORT') ?: '5433',
        getenv('PG_DATABASE') ?: 'gest_budget_pap'
    ),
    getenv('PG_USERNAME') ?: 'postgres',
    getenv('PG_PASSWORD') !== false ? (string) getenv('PG_PASSWORD') : 'admin',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pgsql->exec("SET client_encoding TO 'UTF8'");
$pgsql->exec('SET session_replication_role = replica');
$pgsql->exec('SET CONSTRAINTS ALL DEFERRED');

$tablesPg = $pgsql->query(
    "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'spatial_ref_sys' ORDER BY tablename"
)->fetchAll(PDO::FETCH_COLUMN);

$tablesMysql = $mysql->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$tablesMysql = array_map('strval', $tablesMysql);

$rapport = ['ok' => [], 'skip' => [], 'erreurs' => []];

$aCopier = array_values(array_filter(
    $tablesPg,
    fn (string $table) => $table !== 'migrations' && in_array($table, $tablesMysql, true)
));

if ($aCopier !== []) {
    $pgsql->exec('TRUNCATE TABLE '.implode(', ', array_map('ident', $aCopier)).' RESTART IDENTITY CASCADE');
}

foreach ($tablesPg as $table) {
    if ($table === 'migrations') {
        continue;
    }
    if (! in_array($table, $tablesMysql, true)) {
        $rapport['skip'][] = "{$table} (absente de MySQL)";
        continue;
    }

    $pgCols = $pgsql->query(
        'SELECT column_name, data_type, udt_name, is_nullable
         FROM information_schema.columns
         WHERE table_schema = \'public\' AND table_name = '.$pgsql->quote($table).'
         ORDER BY ordinal_position'
    )->fetchAll(PDO::FETCH_ASSOC);
    $pgMeta = [];
    foreach ($pgCols as $col) {
        $pgMeta[$col['column_name']] = $col;
    }

    $myCols = $mysql->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $myNames = array_column($myCols, 'Field');
    $communes = array_values(array_intersect(array_keys($pgMeta), $myNames));
    if ($communes === []) {
        $rapport['skip'][] = "{$table} (aucune colonne commune)";
        continue;
    }

    $select = implode(', ', array_map(fn ($c) => '`'.$c.'`', $communes));
    $rows = $mysql->query("SELECT {$select} FROM `{$table}`");
    $placeholders = implode(', ', array_fill(0, count($communes), '?'));
    $insertSql = 'INSERT INTO '.ident($table).' ('.implode(', ', array_map('ident', $communes)).") VALUES ({$placeholders})";
    $stmt = $pgsql->prepare($insertSql);

    $n = 0;
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $values = [];
        foreach ($communes as $col) {
            $values[] = convertir($row[$col] ?? null, $pgMeta[$col]);
        }
        try {
            $stmt->execute($values);
            $n++;
        } catch (PDOException $e) {
            $rapport['erreurs'][] = "{$table}#{$n}: ".$e->getMessage();
            throw $e;
        }
    }

    $rapport['ok'][] = "{$table}: {$n}";
}

$seqs = $pgsql->query(
    "SELECT n.nspname || '.' || c.relname AS seq,
            t.relname AS table_name,
            a.attname AS column_name
     FROM pg_class c
     JOIN pg_namespace n ON n.oid = c.relnamespace
     JOIN pg_depend d ON d.objid = c.oid AND d.deptype = 'a'
     JOIN pg_class t ON t.oid = d.refobjid
     JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
     WHERE c.relkind = 'S' AND n.nspname = 'public'"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($seqs as $seq) {
    $max = $pgsql->query('SELECT COALESCE(MAX('.ident($seq['column_name']).'), 0) FROM '.ident($seq['table_name']))->fetchColumn();
    $next = max(1, (int) $max);
    $pgsql->exec('SELECT setval('.$pgsql->quote($seq['seq']).', '.$next.', '.((int) $max > 0 ? 'true' : 'false').')');
}

$pgsql->exec('SET session_replication_role = DEFAULT');

foreach ($rapport['ok'] as $ligne) {
    fwrite(STDOUT, $ligne.PHP_EOL);
}
foreach ($rapport['skip'] as $ligne) {
    fwrite(STDOUT, 'SKIP '.$ligne.PHP_EOL);
}
fwrite(STDOUT, 'Tables copiées : '.count($rapport['ok']).PHP_EOL);

function ident(string $name): string
{
    return '"'.str_replace('"', '""', $name).'"';
}

function convertir(mixed $value, array $meta): mixed
{
    if ($value === null) {
        return null;
    }
    if (is_string($value) && ($value === '' || $value === '0000-00-00' || str_starts_with($value, '0000-00-00'))) {
        $type = $meta['data_type'];
        if (in_array($type, ['date', 'timestamp without time zone', 'timestamp with time zone', 'time without time zone', 'integer', 'bigint', 'smallint', 'numeric', 'double precision', 'real', 'boolean', 'json', 'jsonb', 'uuid'], true)) {
            return null;
        }
        if ($value === '') {
            return $meta['is_nullable'] === 'YES' ? null : $value;
        }
    }

    $udt = $meta['udt_name'];
    $type = $meta['data_type'];

    if ($type === 'boolean' || $udt === 'bool') {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 't', 'true', 'TRUE', 'on'], true);
    }

    if (in_array($type, ['json', 'jsonb'], true) && is_string($value) && $value === '') {
        return null;
    }

    if ($udt === 'bytea' && is_string($value)) {
        return $value;
    }

    return $value;
}
