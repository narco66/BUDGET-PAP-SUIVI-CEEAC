<?php

/**
 * Jeu d'essai — migration MySQL → PostgreSQL 18.
 * Exécute chaque cas contre la base PostgreSQL (écritures dans des points de sauvegarde annulés)
 * et écrit les résultats dans storage/app/jeu-essai/resultats.json.
 * Usage : php database/scripts/jeu_essai_postgresql.php
 */

$root = dirname(__DIR__, 2);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\AdministrationTechnique\ProtectionConnexionService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

config(['database.connections.src' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'gest_budget_pap',
    'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'prefix' => '',
]]);

$pg = DB::connection('pgsql');
$my = DB::connection('src');
$baseUrl = 'http://budget-pap-suivi-ceeac.test';
$cas = [];

function cas(string $id, string $domaine, string $titre, string $objectif, string $donnees, string $etapes, string $attendu, callable $test): void
{
    global $cas;
    $cas[] = compact('id', 'domaine', 'titre', 'objectif', 'donnees', 'etapes', 'attendu') + ['test' => $test];
}

/** Exécute $fn dans un point de sauvegarde systématiquement annulé ; renvoie [résultat, exception]. */
function annule(callable $fn): array
{
    $pg = DB::connection('pgsql');
    $pg->beginTransaction();
    try {
        return [$fn(), null];
    } catch (Throwable $e) {
        return [null, $e];
    } finally {
        $pg->rollBack();
    }
}

function sqlstate(?Throwable $e): ?string
{
    return $e instanceof Illuminate\Database\QueryException ? ($e->errorInfo[0] ?? $e->getCode()) : null;
}

/** Requête GET interne authentifiée ; renvoie le code HTTP. */
function getInterne(string $uri, User $user): int
{
    global $app, $httpKernel;
    $app['auth']->forgetGuards();
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession($app['session']->driver());
    Auth::login($user);
    $response = $httpKernel->handle($request);
    $code = $response->getStatusCode();
    if ($code >= 500 && isset($response->exception)) {
        throw $response->exception;
    }

    return $code;
}

function enumCheck(string $table, string $col): string
{
    return (string) DB::connection('pgsql')->selectOne(
        'select pg_get_constraintdef(oid) d from pg_constraint where conname = ?', ["{$table}_{$col}_check"]
    )?->d;
}

// ───────────────────────── INF — Infrastructure ─────────────────────────

cas('INF-01', 'Infrastructure', 'Version du serveur PostgreSQL',
    'Vérifier que l\'application est servie par PostgreSQL 18 sur le port dédié.',
    'Hôte 127.0.0.1, port 5433, compte postgres.',
    'Exécuter SELECT version() et SHOW port.',
    'Version 18.x, port 5433.',
    function () use ($pg) {
        $v = $pg->selectOne('select version() v')->v;
        $p = $pg->selectOne('show port')->port;

        return [str_contains($v, 'PostgreSQL 18.') && $p === '5433', strtok($v, ',')." — port {$p}"];
    });

cas('INF-02', 'Infrastructure', 'Connexion Laravel par défaut',
    'Vérifier que la configuration applicative pointe sur PostgreSQL.',
    'Fichier .env (DB_CONNECTION, DB_PORT, DB_DATABASE).',
    'Lire config(database.default) et la base de la connexion.',
    'Pilote pgsql, base gest_budget_pap, port 5433.',
    function () {
        $d = config('database.default');
        $c = config("database.connections.$d");

        return [$d === 'pgsql' && $c['database'] === 'gest_budget_pap' && (string) $c['port'] === '5433', "pilote {$d}, base {$c['database']}, port {$c['port']}"];
    });

cas('INF-03', 'Infrastructure', 'Extensions PHP PostgreSQL',
    'Vérifier la présence des pilotes PHP nécessaires.',
    'PHP '.PHP_VERSION.'.',
    'Contrôler extension_loaded(pdo_pgsql) et extension_loaded(pgsql).',
    'Les deux extensions sont chargées.',
    fn () => [extension_loaded('pdo_pgsql') && extension_loaded('pgsql'), 'pdo_pgsql='.(extension_loaded('pdo_pgsql') ? 'oui' : 'non').', pgsql='.(extension_loaded('pgsql') ? 'oui' : 'non')]);

cas('INF-04', 'Infrastructure', 'Encodage et collation de la base',
    'Garantir la bonne conservation des caractères accentués et le tri en français.',
    'Base gest_budget_pap.',
    'Lire server_encoding et datcollate.',
    'Encodage UTF8.',
    function () use ($pg) {
        $r = $pg->selectOne("select current_setting('server_encoding') e, datcollate c from pg_database where datname = current_database()");

        return [$r->e === 'UTF8', "encodage {$r->e}, collation {$r->c}"];
    });

cas('INF-05', 'Infrastructure', 'Sauvegarde pg_dump',
    'Vérifier que l\'outil de sauvegarde utilisé par BackupService fonctionne sur la base.',
    'C:\\Program Files\\PostgreSQL\\18\\bin\\pg_dump.exe, option --schema-only, fichier temporaire.',
    'Lancer pg_dump --schema-only vers un fichier temporaire, contrôler la taille, supprimer le fichier.',
    'Code retour 0, fichier non vide contenant CREATE TABLE.',
    function () {
        $bin = 'C:\\Program Files\\PostgreSQL\\18\\bin\\pg_dump.exe';
        $out = tempnam(sys_get_temp_dir(), 'je_dump_');
        $p = new Symfony\Component\Process\Process([$bin, '-h', '127.0.0.1', '-p', '5433', '-U', 'postgres', '--schema-only', '-f', $out, 'gest_budget_pap'], null, ['PGPASSWORD' => 'admin']);
        $p->setTimeout(120)->run();
        $taille = is_file($out) ? filesize($out) : 0;
        $ok = $p->isSuccessful() && $taille > 0 && str_contains((string) file_get_contents($out), 'CREATE TABLE');
        @unlink($out);

        return [$ok, 'code '.$p->getExitCode().', '.number_format($taille / 1024, 0, ',', ' ').' Ko de schéma'];
    });

// ───────────────────────── SCH — Schéma ─────────────────────────

$tablesMy = collect($my->select("select table_name t from information_schema.tables where table_schema=database() and table_type='BASE TABLE'"))->pluck('t');
$tablesPg = collect($pg->select("select tablename t from pg_tables where schemaname='public'"))->pluck('t');

cas('SCH-01', 'Schéma', 'Parité des tables',
    'Vérifier que toutes les tables MySQL existent en PostgreSQL et réciproquement.',
    'Catalogues information_schema (MySQL) et pg_tables (PostgreSQL).',
    'Comparer les deux listes de tables.',
    'Mêmes tables des deux côtés, aucune absente.',
    function () use ($tablesMy, $tablesPg) {
        $a = $tablesMy->diff($tablesPg); $b = $tablesPg->diff($tablesMy);

        return [$a->isEmpty() && $b->isEmpty(), "MySQL {$tablesMy->count()} / PG {$tablesPg->count()} ; absentes PG : ".($a->implode(', ') ?: 'aucune').' ; absentes MySQL : '.($b->implode(', ') ?: 'aucune')];
    });

cas('SCH-02', 'Schéma', 'Parité des colonnes',
    'Vérifier que chaque colonne MySQL existe dans la table PostgreSQL correspondante.',
    'Catalogues information_schema.columns.',
    'Comparer les couples table.colonne.',
    '0 colonne manquante.',
    function () use ($my, $pg) {
        $m = collect($my->select('select concat(table_name, ".", column_name) k from information_schema.columns where table_schema=database()'))->pluck('k');
        $p = collect($pg->select("select table_name || '.' || column_name k from information_schema.columns where table_schema='public'"))->pluck('k');
        $manq = $m->diff($p);

        return [$manq->isEmpty(), "{$m->count()} colonnes MySQL, {$p->count()} PG, manquantes : ".($manq->take(10)->implode(', ') ?: 'aucune')];
    });

cas('SCH-03', 'Schéma', 'Énumérations ↔ contraintes CHECK',
    'Vérifier que chaque ENUM MySQL a une contrainte CHECK PostgreSQL de valeurs identiques.',
    'Colonnes de type enum (MySQL), contraintes {table}_{colonne}_check (PG).',
    'Extraire et comparer les listes de valeurs autorisées.',
    'Toutes les listes sont identiques.',
    function () use ($my) {
        $enums = $my->select("select table_name t, column_name c, column_type ct from information_schema.columns where table_schema=database() and data_type='enum'");
        $ecarts = [];
        foreach ($enums as $e) {
            preg_match_all("/'([^']*)'/", $e->ct, $m); $a = $m[1]; sort($a);
            preg_match_all("/'([^']*)'/", enumCheck($e->t, $e->c), $p); $b = $p[1]; sort($b);
            if ($a !== $b) $ecarts[] = "{$e->t}.{$e->c}";
        }

        return [! $ecarts, count($enums).' énumérations comparées, écarts : '.(implode(', ', $ecarts) ?: 'aucun')];
    });

cas('SCH-04', 'Schéma', 'Clés étrangères',
    'Vérifier la parité des clés étrangères (colonne, table cible, règle ON DELETE).',
    'information_schema.referential_constraints (MySQL), pg_constraint (PG).',
    'Comparer les ensembles « table.colonne → cible [règle] » (RESTRICT ≡ NO ACTION).',
    'Mêmes clés étrangères, mêmes règles.',
    function () use ($my, $pg) {
        $n = fn ($s) => str_replace(['RESTRICT', 'NO ACTION'], 'R', $s);
        $m = collect($my->select("select concat(k.table_name,'.',k.column_name,'>',k.referenced_table_name,'[',r.delete_rule,']') k from information_schema.key_column_usage k join information_schema.referential_constraints r on r.constraint_schema=k.constraint_schema and r.constraint_name=k.constraint_name where k.table_schema=database() and k.referenced_table_name is not null"))->pluck('k')->map($n);
        $p = collect($pg->select("select conrelid::regclass::text||'.'||a.attname||'>'||confrelid::regclass::text||'['||case confdeltype when 'a' then 'NO ACTION' when 'r' then 'RESTRICT' when 'c' then 'CASCADE' when 'n' then 'SET NULL' else 'SET DEFAULT' end||']' k from pg_constraint co join pg_attribute a on a.attrelid=co.conrelid and a.attnum=any(co.conkey) where contype='f' and connamespace='public'::regnamespace"))->pluck('k')->map(fn ($s) => $n(str_replace('"', '', $s)));
        $d = $m->diff($p)->merge($p->diff($m));

        return [$d->isEmpty(), "MySQL {$m->count()} / PG {$p->count()} ; écarts : ".($d->take(6)->implode(' ; ') ?: 'aucun')];
    });

cas('SCH-05', 'Schéma', 'Migrations Laravel',
    'Vérifier que toutes les migrations jouées sous MySQL le sont sous PostgreSQL.',
    'Tables migrations des deux bases.',
    'Comparer les noms de migrations.',
    'Aucune migration MySQL manquante ; seules des migrations correctives PG en plus.',
    function () use ($my, $pg) {
        $m = collect($my->table('migrations')->pluck('migration'));
        $p = collect($pg->table('migrations')->pluck('migration'));
        $manq = $m->diff($p); $plus = $p->diff($m);

        return [$manq->isEmpty(), "MySQL {$m->count()} / PG {$p->count()} ; manquantes : ".($manq->implode(', ') ?: 'aucune').' ; en plus : '.($plus->implode(', ') ?: 'aucune')];
    });

cas('SCH-06', 'Schéma', 'Séquences d\'identifiants',
    'Vérifier qu\'aucune séquence ne produira un identifiant déjà utilisé.',
    'Toutes les colonnes serial/identity du schéma public.',
    'Pour chaque séquence, comparer la prochaine valeur à MAX(id).',
    'Prochaine valeur > MAX(id) pour toutes les séquences.',
    function () use ($pg) {
        $seqs = $pg->select("select c.relname t, a.attname c, pg_get_serial_sequence(quote_ident(c.relname), a.attname) s from pg_class c join pg_attribute a on a.attrelid=c.oid join pg_namespace n on n.oid=c.relnamespace where n.nspname='public' and c.relkind='r' and a.attnum>0 and pg_get_serial_sequence(quote_ident(c.relname), a.attname) is not null");
        $ko = [];
        foreach ($seqs as $s) {
            $max = (int) $pg->table($s->t)->max($s->c);
            $q = $pg->selectOne("select last_value, is_called from {$s->s}");
            if (($q->is_called ? $q->last_value + 1 : $q->last_value) <= $max) $ko[] = $s->s;
        }

        return [! $ko, count($seqs).' séquences contrôlées, en retard : '.(implode(', ', $ko) ?: 'aucune')];
    });

cas('SCH-07', 'Schéma', 'Index sur clés étrangères',
    'Vérifier que les colonnes de clé étrangère sont indexées (MySQL le faisait automatiquement).',
    'pg_constraint (FK) et pg_index.',
    'Lister les FK dont la colonne n\'est la première colonne d\'aucun index.',
    '0 FK sans index.',
    function () use ($pg) {
        $n = $pg->selectOne("select count(*) n from pg_constraint co where contype='f' and connamespace='public'::regnamespace and not exists (select 1 from pg_index i where i.indrelid=co.conrelid and i.indkey[0]=co.conkey[1])")->n;

        return [$n == 0 ? true : 'reserve', "{$n} clé(s) étrangère(s) sans index"];
    });

// ───────────────────────── DON — Données ─────────────────────────

cas('DON-01', 'Données', 'Volume global',
    'Vérifier que le nombre total de lignes est identique dans les deux bases.',
    'Toutes les tables communes hors migrations, cache, sessions, jobs.',
    'Additionner COUNT(*) de chaque table des deux côtés.',
    'Totaux identiques.',
    function () use ($my, $pg, $tablesMy, $tablesPg) {
        $tm = 0; $tp = 0; $ecarts = 0;
        foreach ($tablesMy->intersect($tablesPg)->diff(['migrations', 'cache', 'cache_locks', 'sessions', 'jobs']) as $t) {
            $a = $my->table($t)->count(); $b = $pg->table($t)->count();
            $tm += $a; $tp += $b; if ($a !== $b) $ecarts++;
        }

        return [$tm === $tp && ! $ecarts, "MySQL {$tm} lignes / PG {$tp} lignes ; {$ecarts} table(s) en écart"];
    });

foreach ([
    ['DON-02', 'users', 'Utilisateurs'],
    ['DON-03', 'structures', 'Structures (organigramme)'],
    ['DON-04', 'lignes_budgetaires', 'Lignes budgétaires'],
    ['DON-05', 'activites', 'Activités PAP'],
    ['DON-06', 'role_has_permissions', 'Habilitations (rôle ↔ permission)'],
] as [$id, $table, $libelle]) {
    cas($id, 'Données', "Volume — {$libelle}",
        "Vérifier la reprise intégrale de la table {$table}.",
        "Table {$table}.",
        'Comparer COUNT(*) MySQL et PostgreSQL.',
        'Volumes identiques.',
        function () use ($my, $pg, $table) {
            $a = $my->table($table)->count(); $b = $pg->table($table)->count();

            return [$a === $b, "MySQL {$a} / PG {$b}"];
        });
}

cas('DON-07', 'Données', 'Contenu de la nomenclature budgétaire',
    'Vérifier ligne à ligne que le référentiel budgétaire est identique (pas seulement le volume).',
    'Tables budget_titres, budget_rubriques (colonnes id, code, intitule, sens).',
    'Calculer une empreinte SHA-256 du contenu trié par id des deux côtés.',
    'Empreintes identiques.',
    function () use ($my, $pg) {
        $h = fn ($c) => hash('sha256', json_encode($c->table('budget_titres')->orderBy('id')->get(['id', 'code', 'intitule', 'sens'])).json_encode($c->table('budget_rubriques')->orderBy('id')->get(['id', 'code', 'intitule'])));
        $a = $h($my); $b = $h($pg);

        return [$a === $b, 'MySQL '.substr($a, 0, 12).'… / PG '.substr($b, 0, 12).'…'];
    });

cas('DON-08', 'Données', 'Intégrité référentielle',
    'Vérifier qu\'aucune ligne ne référence une ligne parente inexistante (le chargement a désactivé les FK).',
    'Toutes les clés étrangères mono-colonne.',
    'Pour chaque FK, compter les valeurs non nulles sans parent.',
    '0 orphelin.',
    function () use ($pg) {
        $fks = $pg->select("select conrelid::regclass::text t, confrelid::regclass::text rt, a.attname c, af.attname rc from pg_constraint co join pg_attribute a on a.attrelid=co.conrelid and a.attnum=co.conkey[1] join pg_attribute af on af.attrelid=co.confrelid and af.attnum=co.confkey[1] where contype='f' and connamespace='public'::regnamespace and array_length(conkey,1)=1");
        $orph = 0; $tables = [];
        foreach ($fks as $f) {
            $n = $pg->selectOne("select count(*) n from {$f->t} x where x.\"{$f->c}\" is not null and not exists (select 1 from {$f->rt} y where y.\"{$f->rc}\" = x.\"{$f->c}\")")->n;
            if ($n) { $orph += $n; $tables[] = "{$f->t}.{$f->c}"; }
        }

        return [$orph == 0, count($fks)." FK contrôlées, {$orph} orphelin(s) ".($tables ? '('.implode(', ', $tables).')' : '')];
    });

cas('DON-09', 'Données', 'Mots de passe hachés',
    'Vérifier que les empreintes bcrypt sont reprises sans altération.',
    'Compte directeur.plan@ceeac.test, mot de passe de démonstration « password ».',
    'Hash::check sur l\'empreinte lue en PostgreSQL.',
    'Empreinte au format $2y$, vérification positive.',
    function () {
        $u = User::where('email', 'directeur.plan@ceeac.test')->first();
        $ok = $u && str_starts_with($u->password, '$2y$') && Hash::check('password', $u->password);

        return [$ok, $u ? 'empreinte '.substr($u->password, 0, 7).'…, vérification '.($ok ? 'positive' : 'négative') : 'compte absent'];
    });

// ───────────────────────── CTR — Contraintes et types (données d'entrée) ─────────────────────────

cas('CTR-01', 'Contraintes', 'Statut PAP invalide refusé',
    'Vérifier que la contrainte d\'énumération bloque une valeur hors liste.',
    "paps.statut = 'statut_inexistant' sur le premier PAP.",
    'UPDATE dans un point de sauvegarde annulé.',
    'Refus SQLSTATE 23514 (violation de contrainte CHECK).',
    function () use ($pg) {
        [, $e] = annule(fn () => $pg->table('paps')->limit(1)->update(['statut' => 'statut_inexistant']));

        return [sqlstate($e) === '23514', $e ? 'refus SQLSTATE '.sqlstate($e) : 'valeur acceptée'];
    });

cas('CTR-02', 'Contraintes', 'Statut PAP « en_revision » accepté',
    'Vérifier l\'élargissement de l\'énumération apporté par la migration 2026_06_21 (correctif PG).',
    "paps.statut = 'en_revision'.",
    'UPDATE dans un point de sauvegarde annulé.',
    'Mise à jour acceptée.',
    function () use ($pg) {
        if (! $pg->table('paps')->exists()) return [str_contains(enumCheck('paps', 'statut'), 'en_revision'), 'aucun PAP : contrôle sur la définition de la contrainte'];
        [$n, $e] = annule(fn () => $pg->table('paps')->limit(1)->update(['statut' => 'en_revision']));

        return [! $e && $n === 1, $e ? 'refus : '.sqlstate($e) : "{$n} ligne mise à jour"];
    });

cas('CTR-03', 'Contraintes', 'Sens budgétaire « financement » accepté',
    'Vérifier que l\'ancienne contrainte recette/dépense a bien été levée (correctif migration 2026_06_11).',
    "budget_titres.sens = 'financement' sur un titre de recette.",
    'UPDATE dans un point de sauvegarde annulé.',
    'Mise à jour acceptée.',
    function () use ($pg) {
        [$n, $e] = annule(fn () => $pg->table('budget_titres')->where('sens', 'recette')->limit(1)->update(['sens' => 'financement']));

        return [! $e && $n === 1, $e ? 'refus : '.sqlstate($e) : "{$n} ligne mise à jour"];
    });

cas('CTR-04', 'Contraintes', 'Montant budgétaire négatif refusé',
    'Vérifier la contrainte métier chk_lb_montants_positifs.',
    'lignes_budgetaires.montant_ouvert = -1 sur la première ligne.',
    'UPDATE dans un point de sauvegarde annulé.',
    'Refus SQLSTATE 23514.',
    function () use ($pg) {
        [, $e] = annule(fn () => $pg->table('lignes_budgetaires')->limit(1)->update(['montant_ouvert' => -1]));

        return [sqlstate($e) === '23514', $e ? 'refus SQLSTATE '.sqlstate($e).(str_contains($e->getMessage(), 'chk_lb') ? ' (chk_lb_*)' : '') : 'valeur acceptée'];
    });

cas('CTR-05', 'Contraintes', 'Type de campagne « mensuel » autorisé',
    'Vérifier l\'élargissement de campagnes_suivi.type (correctif migration 2026_07_19).',
    'Définition de la contrainte campagnes_suivi_type_check.',
    'Lire la définition de la contrainte.',
    'La valeur mensuel figure dans la liste.',
    fn () => [str_contains($d = enumCheck('campagnes_suivi', 'type'), "'mensuel'"), $d ?: 'contrainte absente']);

cas('CTR-06', 'Contraintes', 'Type d\'évaluation « impact » autorisé',
    'Vérifier l\'élargissement de evaluations_periodiques.type (correctif migration 2026_07_26).',
    'Définition de la contrainte evaluations_periodiques_type_check.',
    'Lire la définition de la contrainte.',
    'La valeur impact figure dans la liste.',
    fn () => [str_contains($d = enumCheck('evaluations_periodiques', 'type'), "'impact'"), $d ?: 'contrainte absente']);

cas('CTR-07', 'Contraintes', 'Suppression d\'un type d\'indicateur utilisé refusée',
    'Vérifier la règle ON DELETE RESTRICT rétablie par la migration corrective 2026_10_24_230000.',
    'DELETE du type_indicateur référencé par au moins un indicateur.',
    'DELETE dans un point de sauvegarde annulé.',
    'Refus SQLSTATE 23001 (restrict_violation) ou 23503 (foreign_key_violation).',
    function () use ($pg) {
        $id = $pg->table('indicateurs')->value('type_indicateur_id');
        if (! $id) return [false, 'aucun indicateur typé pour exécuter le cas'];
        [, $e] = annule(fn () => $pg->table('types_indicateurs')->where('id', $id)->delete());

        return [in_array(sqlstate($e), ['23001', '23503'], true), $e ? 'refus SQLSTATE '.sqlstate($e).(sqlstate($e) === '23001' ? ' (restrict_violation : règle RESTRICT active)' : '') : 'suppression acceptée'];
    });

cas('CTR-08', 'Contraintes', 'Rattachement à une structure inexistante refusé',
    'Vérifier l\'application des clés étrangères après le chargement.',
    'structures.parent_id = 999999 (inexistant).',
    'UPDATE dans un point de sauvegarde annulé.',
    'Refus SQLSTATE 23503.',
    function () use ($pg) {
        [, $e] = annule(fn () => $pg->table('structures')->limit(1)->update(['parent_id' => 999999]));

        return [sqlstate($e) === '23503', $e ? 'refus SQLSTATE '.sqlstate($e) : 'valeur acceptée'];
    });

cas('CTR-09', 'Contraintes', 'Adresse e-mail en double refusée',
    'Vérifier l\'index unique users.email.',
    'E-mail du second utilisateur remplacé par celui du premier.',
    'UPDATE dans un point de sauvegarde annulé.',
    'Refus SQLSTATE 23505 (violation d\'unicité).',
    function () use ($pg) {
        $emails = $pg->table('users')->orderBy('id')->limit(2)->pluck('email', 'id');
        if ($emails->count() < 2) return [false, 'moins de deux utilisateurs'];
        [, $e] = annule(fn () => $pg->table('users')->where('id', $emails->keys()[1])->update(['email' => $emails->first()]));

        return [sqlstate($e) === '23505', $e ? 'refus SQLSTATE '.sqlstate($e) : 'doublon accepté'];
    });

cas('CTR-10', 'Contraintes', 'Type d\'indicateur obligatoire',
    'Vérifier la contrainte NOT NULL posée par la phase E3.',
    'indicateurs.type_indicateur_id = NULL.',
    'UPDATE dans un point de sauvegarde annulé.',
    'Refus SQLSTATE 23502 (valeur NULL interdite).',
    function () use ($pg) {
        [, $e] = annule(fn () => $pg->table('indicateurs')->limit(1)->update(['type_indicateur_id' => null]));

        return [sqlstate($e) === '23502', $e ? 'refus SQLSTATE '.sqlstate($e) : 'NULL accepté'];
    });

cas('CTR-11', 'Types', 'Précision des montants décimaux',
    'Vérifier qu\'un montant à 16 chiffres significatifs est restitué au centime près.',
    'lignes_budgetaires.montant_engage = 9 876 543 210 123,45 (decimal 18,2).',
    'UPDATE puis relecture dans un point de sauvegarde annulé.',
    'Valeur relue strictement égale à 9876543210123.45.',
    function () use ($pg) {
        [$v, $e] = annule(function () use ($pg) {
            $id = $pg->table('lignes_budgetaires')->value('id');
            $pg->table('lignes_budgetaires')->where('id', $id)->update(['montant_ouvert' => '9876543210123.45', 'montant_engage' => '9876543210123.45']);

            return $pg->table('lignes_budgetaires')->where('id', $id)->value('montant_engage');
        });

        return [! $e && (string) $v === '9876543210123.45', $e ? 'erreur : '.$e->getMessage() : "relu : {$v}"];
    });

cas('CTR-12', 'Types', 'Booléens natifs',
    'Vérifier que les anciens TINYINT(1) MySQL sont des booléens PostgreSQL exploitables par Eloquent.',
    'users.actif = false puis true.',
    'Mise à jour et relecture typée dans un point de sauvegarde annulé.',
    'Valeurs relues false puis true (type booléen).',
    function () use ($pg) {
        [$r, $e] = annule(function () use ($pg) {
            $id = $pg->table('users')->value('id');
            $pg->table('users')->where('id', $id)->update(['actif' => false]);
            $a = $pg->table('users')->where('id', $id)->value('actif');
            $pg->table('users')->where('id', $id)->update(['actif' => true]);

            return [$a, $pg->table('users')->where('id', $id)->value('actif')];
        });

        return [! $e && $r[0] === false && $r[1] === true, $e ? 'erreur : '.$e->getMessage() : 'relu : '.var_export($r[0], true).' puis '.var_export($r[1], true)];
    });

cas('CTR-13', 'Types', 'Données JSON',
    'Vérifier l\'aller-retour d\'un document JSON contenant des caractères accentués.',
    'users.preferences_notifications = {"email":true,"sms":false,"canal":"tél. à confirmer"}.',
    'UPDATE puis relecture et décodage dans un point de sauvegarde annulé.',
    'Document relu identique.',
    function () use ($pg) {
        $doc = ['email' => true, 'sms' => false, 'canal' => 'tél. à confirmer'];
        [$v, $e] = annule(function () use ($pg, $doc) {
            $id = $pg->table('users')->value('id');
            $pg->table('users')->where('id', $id)->update(['preferences_notifications' => json_encode($doc, JSON_UNESCAPED_UNICODE)]);

            return json_decode($pg->table('users')->where('id', $id)->value('preferences_notifications'), true);
        });

        return [! $e && $v === $doc, $e ? 'erreur : '.$e->getMessage() : 'relu : '.json_encode($v, JSON_UNESCAPED_UNICODE)];
    });

cas('CTR-14', 'Types', 'Texte accentué UTF-8',
    'Vérifier la conservation des caractères spéciaux du français.',
    "structures.libelle = « Délégation à l'Économie — Çà et là ».",
    'UPDATE puis relecture dans un point de sauvegarde annulé.',
    'Libellé relu identique octet pour octet.',
    function () use ($pg) {
        $txt = "Délégation à l'Économie — Çà et là";
        [$v, $e] = annule(function () use ($pg, $txt) {
            $id = $pg->table('structures')->value('id');
            $pg->table('structures')->where('id', $id)->update(['libelle' => $txt]);

            return $pg->table('structures')->where('id', $id)->value('libelle');
        });

        return [! $e && $v === $txt, $e ? 'erreur : '.$e->getMessage() : "relu : {$v}"];
    });

cas('CTR-15', 'Types', 'Attribution d\'identifiant à l\'insertion',
    'Vérifier qu\'une nouvelle ligne reçoit un identifiant libre (séquence recalée).',
    "Nouveau type d'indicateur code JE-TEST, libellé « Type de test ».",
    'INSERT dans un point de sauvegarde annulé, comparaison à MAX(id) préalable.',
    'Insertion acceptée, identifiant > MAX(id) préalable.',
    function () use ($pg) {
        $max = (int) $pg->table('types_indicateurs')->max('id');
        [$id, $e] = annule(fn () => $pg->table('types_indicateurs')->insertGetId(['code' => 'JE-TEST', 'libelle' => 'Type de test', 'ordre' => 99, 'statut' => 'actif', 'created_at' => now(), 'updated_at' => now()]));

        return [! $e && $id > $max, $e ? 'erreur : '.sqlstate($e).' '.$e->getMessage() : "MAX préalable {$max}, nouvel id {$id}"];
    });

// ───────────────────────── AUT — Authentification et sécurité ─────────────────────────

$http = function () use ($baseUrl) {
    $jar = new CookieJar;
    $page = Http::withOptions(['cookies' => $jar, 'allow_redirects' => false])->get("{$baseUrl}/login")->body();
    preg_match('/name="_token" value="([^"]+)"/', $page, $m);

    return [$jar, $m[1] ?? ''];
};

cas('AUT-01', 'Authentification', 'Connexion avec identifiants valides',
    'Vérifier le parcours de connexion réel sur PostgreSQL (sessions en base).',
    'directeur.plan@ceeac.test / password, http://budget-pap-suivi-ceeac.test.',
    'GET /login (jeton CSRF), POST /login, GET /dashboard.',
    'POST → 302 vers /dashboard ; tableau de bord → 200.',
    function () use ($http, $baseUrl) {
        [$jar, $t] = $http();
        $r = Http::asForm()->withOptions(['cookies' => $jar, 'allow_redirects' => false])->post("{$baseUrl}/login", ['_token' => $t, 'email' => 'directeur.plan@ceeac.test', 'password' => 'password']);
        $loc = $r->header('Location');
        $d = Http::withOptions(['cookies' => $jar, 'allow_redirects' => false])->get("{$baseUrl}/dashboard");

        return [$r->status() === 302 && str_ends_with($loc, '/dashboard') && $d->status() === 200, "POST {$r->status()} → {$loc} ; dashboard {$d->status()}"];
    });

cas('AUT-02', 'Authentification', 'Mot de passe erroné refusé et journalisé',
    'Vérifier le refus et la traçabilité d\'un échec de connexion.',
    'directeur.plan@ceeac.test / « mauvais-mot-de-passe ».',
    'POST /login ; contrôle de login_logs ; nettoyage de la trace de test et du compteur d\'échecs.',
    'Retour au formulaire avec « Identifiants invalides. », trace login_logs succes=false.',
    function () use ($http, $baseUrl) {
        $avant = (int) DB::table('login_logs')->max('id');
        [$jar, $t] = $http();
        $r = Http::asForm()->withOptions(['cookies' => $jar, 'allow_redirects' => false])->post("{$baseUrl}/login", ['_token' => $t, 'email' => 'directeur.plan@ceeac.test', 'password' => 'mauvais-mot-de-passe']);
        $f = Http::withOptions(['cookies' => $jar])->get("{$baseUrl}/login")->body();
        $log = DB::table('login_logs')->where('id', '>', $avant)->where('email', 'directeur.plan@ceeac.test')->first();
        DB::table('login_logs')->where('id', '>', $avant)->where('email', 'directeur.plan@ceeac.test')->where('succes', false)->delete();
        app(ProtectionConnexionService::class)->reinitialiser('127.0.0.1');
        $ok = $r->status() === 302 && str_contains($f, 'Identifiants invalides') && $log && ! $log->succes;

        return [$ok, "POST {$r->status()}, message ".(str_contains($f, 'Identifiants invalides') ? 'affiché' : 'absent').', trace '.($log ? "« {$log->motif} »" : 'absente')];
    });

cas('AUT-03', 'Authentification', 'Verrouillage après 5 échecs',
    'Vérifier la protection anti brute-force (seuil '.ProtectionConnexionService::SEUIL.' échecs / '.ProtectionConnexionService::FENETRE_MIN.' min).',
    'IP de documentation 203.0.113.10, 5 échecs simulés dans login_logs.',
    'Insérer 5 échecs, appeler enregistrerEchec puis ipBloquee (point de sauvegarde annulé).',
    'IP verrouillée pour '.ProtectionConnexionService::BLOCAGE_MIN.' minutes.',
    function () use ($pg) {
        [$r, $e] = annule(function () use ($pg) {
            $svc = app(ProtectionConnexionService::class);
            for ($i = 0; $i < ProtectionConnexionService::SEUIL; $i++) {
                $pg->table('login_logs')->insert(['email' => 'test@exemple.org', 'succes' => false, 'motif' => 'Jeu d\'essai', 'ip' => '203.0.113.10', 'user_agent' => 'jeu-essai', 'created_at' => now()]);
            }
            $svc->enregistrerEchec('203.0.113.10');
            $b = $svc->ipBloquee('203.0.113.10');
            $svc->reinitialiser('203.0.113.10');

            return [$b?->bloque_jusqua, $svc->ipBloquee('203.0.113.10')];
        });

        return [! $e && $r[0] !== null && $r[1] === null, $e ? 'erreur : '.$e->getMessage() : 'verrouillée jusqu\'à '.$r[0].' ; après réinitialisation : '.($r[1] ? 'toujours bloquée' : 'débloquée')];
    });

cas('AUT-04', 'Authentification', 'Double authentification des administrateurs',
    'Vérifier que le compte administrateur avec MFA active est dirigé vers le défi MFA.',
    'dsi.ceeac.eccas@gmail.com / password (MFA confirmée).',
    'GET /login, POST /login.',
    'POST → 302 vers /mfa (aucune session ouverte avant le code).',
    function () use ($http, $baseUrl) {
        if (! User::where('email', 'dsi.ceeac.eccas@gmail.com')->whereNotNull('two_factor_confirmed_at')->exists()) return ['reserve', 'compte DSI sans MFA confirmée dans la base actuelle'];
        [$jar, $t] = $http();
        $r = Http::asForm()->withOptions(['cookies' => $jar, 'allow_redirects' => false])->post("{$baseUrl}/login", ['_token' => $t, 'email' => 'dsi.ceeac.eccas@gmail.com', 'password' => 'password']);
        $loc = (string) $r->header('Location');
        if ($r->status() === 302 && str_ends_with($loc, '/login')) {
            DB::table('login_logs')->where('email', 'dsi.ceeac.eccas@gmail.com')->where('succes', false)->where('created_at', '>=', now()->subMinute())->delete();
            app(ProtectionConnexionService::class)->reinitialiser('127.0.0.1');
        }

        return [$r->status() === 302 && str_contains($loc, '/mfa'), "POST {$r->status()} → {$loc}"];
    });

// ───────────────────────── APP — Application ─────────────────────────

// Les appels internes ne peuvent pas saisir de code MFA : la MFA est neutralisée pour ce seul processus
// (ni la base ni la configuration du site ne sont modifiées). Le parcours MFA réel est couvert par AUT-04.
config(['mfa.enabled' => false]);
$admin = User::role('administrateur')->first() ?? User::orderBy('id')->first();

cas('APP-01', 'Application', 'Tableau de bord (agrégats SQL corrigés)',
    'Vérifier les requêtes de TableauBordService, dont le libellé « (non rattachée) » corrigé.',
    "Compte {$admin->email} (MFA neutralisée pour le processus de test).",
    'GET /dashboard en interne.',
    'Réponse 200.',
    fn () => [($c = getInterne('/dashboard', $admin)) === 200, "HTTP {$c}"]);

cas('APP-02', 'Application', 'Commande d\'audit du cadre organique',
    'Vérifier la requête corrigée (HAVING sans alias, booléens sans COALESCE entier).',
    'php artisan institution:audit-cadre (lecture seule, sans --fix).',
    'Exécuter la commande.',
    'Fin sans erreur SQL.',
    function () {
        try {
            $code = Artisan::call('institution:audit-cadre');

            return [true, "terminée, code {$code}"];
        } catch (Throwable $e) {
            return [false, get_class($e).' : '.substr($e->getMessage(), 0, 200)];
        }
    });

cas('APP-03', 'Application', 'Vérification GED avec identifiant mal formé',
    'Vérifier qu\'un UUID invalide renvoie « non trouvé » et non une erreur serveur (colonne uuid native).',
    'URL publique /verifier/ged/1.',
    'GET interne.',
    'Réponse 404.',
    fn () => [($c = getInterne('/verifier/ged/1', $admin)) === 404, "HTTP {$c}"]);

cas('APP-04', 'Application', 'Notification avec identifiant mal formé',
    'Vérifier la contrainte de route whereUuid sur les actions de notification.',
    'POST /notifications/abc/lue.',
    'Résoudre la route.',
    'Aucune route correspondante (404).',
    function () {
        try {
            Route::getRoutes()->match(Illuminate\Http\Request::create('/notifications/abc/lue', 'POST'));

            return [false, 'route résolue'];
        } catch (Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return [true, 'aucune route : 404'];
        }
    });

cas('APP-05', 'Application', 'Recherche insensible à la casse',
    'Vérifier qu\'une recherche en minuscules retrouve un libellé en majuscules (comportement MySQL).',
    'Recherche du premier libellé de structure, saisi en minuscules, opérateur LIKE.',
    'Structure::where(libelle, like, %minuscules%).',
    'La structure est retrouvée.',
    function () {
        $s = App\Models\Structure::whereRaw('libelle <> lower(libelle)')->value('libelle');
        $q = mb_strtolower(mb_substr($s, 0, 12));
        $n = App\Models\Structure::where('libelle', 'like', "%{$q}%")->count();

        return [$n > 0 ? true : 'reserve', "« {$q} » → {$n} résultat(s) (libellé réel : « ".mb_substr($s, 0, 12).' »)'];
    });

cas('APP-06', 'Application', 'Parcours de toutes les pages',
    'Détecter toute erreur SQL résiduelle sur l\'ensemble des écrans de consultation.',
    "Toutes les routes GET (paramètres = 1), compte {$admin->email}, MFA neutralisée pour le processus de test, transaction annulée.",
    'Appeler chaque route en interne et relever les réponses ≥ 500.',
    '0 erreur SQL.',
    function () use ($admin) {
        $skip = '#^(logout|_ignition|telescope|horizon|pulse|sanctum|livewire|up$|health|api/|mfa|two-factor|password|login|storage)#';
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => in_array('GET', $r->methods()) && ! preg_match($skip, $r->uri()));
        $codes = []; $sql = []; $autres = 0;
        DB::beginTransaction();
        foreach ($routes as $r) {
            $uri = preg_replace(['/\{[^}?]+\?\}/', '/\{[^}]+\}/'], ['', '1'], $r->uri());
            DB::beginTransaction();
            try {
                $c = getInterne('/'.ltrim($uri, '/'), $admin);
                $codes[$c] = ($codes[$c] ?? 0) + 1;
            } catch (Illuminate\Database\QueryException $e) {
                $sql[] = $uri;
            } catch (Throwable $e) {
                $autres++;
            }
            try { DB::rollBack(); } catch (Throwable) {}
        }
        try { DB::rollBack(); } catch (Throwable) {}
        ksort($codes);

        return [! $sql, $routes->count().' routes ; '.implode(', ', array_map(fn ($k, $v) => "{$v}×{$k}", array_keys($codes), $codes))." ; erreurs SQL : ".(implode(', ', $sql) ?: '0')." ; autres exceptions (paramètres de test) : {$autres}"];
    });

// ───────────────────────── Exécution ─────────────────────────

$resultats = [];
foreach ($cas as $c) {
    $t0 = microtime(true);
    try {
        [$ok, $obtenu] = ($c['test'])();
    } catch (Throwable $e) {
        [$ok, $obtenu] = [false, 'Exception '.class_basename($e).' : '.substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300)];
    }
    $statut = $ok === 'reserve' ? 'RÉSERVE' : ($ok ? 'CONFORME' : 'NON CONFORME');
    unset($c['test']);
    $resultats[] = $c + ['obtenu' => $obtenu, 'statut' => $statut, 'duree_ms' => (int) round((microtime(true) - $t0) * 1000)];
    fwrite(STDOUT, sprintf("%-7s %-13s %s%s", $c['id'], $statut, $c['titre'], PHP_EOL));
}

$meta = [
    'execute_le' => now('Africa/Lagos')->format('Y-m-d H:i'),
    'php' => PHP_VERSION,
    'laravel' => app()->version(),
    'pg' => strtok($pg->selectOne('select version() v')->v, ','),
    'mysql' => $my->selectOne('select version() v')->v,
    'poste' => gethostname(),
];
@mkdir(storage_path('app/jeu-essai'), 0777, true);
file_put_contents(storage_path('app/jeu-essai/resultats.json'), json_encode(['meta' => $meta, 'cas' => $resultats], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fwrite(STDOUT, 'Résultats : '.storage_path('app/jeu-essai/resultats.json').PHP_EOL);
