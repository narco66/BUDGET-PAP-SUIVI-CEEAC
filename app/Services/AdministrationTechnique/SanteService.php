<?php

namespace App\Services\AdministrationTechnique;

use App\Services\Infrastructure\RedisHealthService;
use App\Models\SystemMetric;
use App\Support\QueueNames;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Centre de supervision technique (Lot T1) — health-check 100 % PHP natif, sans dépendance externe.
 * Mesure l'état de la base, du disque, des files d'attente, des jobs échoués, des sessions et des
 * échecs de connexion. Le statut global est le pire des contrôles (ok < warning < critique).
 */
class SanteService
{
    private const RANG = ['ok' => 0, 'warning' => 1, 'critique' => 2];

    /** Liste des contrôles avec statut, valeur et détail. */
    public function controles(): array
    {
        return [
            $this->controleBase(),
            $this->controleDisque(),
            $this->controleRedis(),
            $this->controleCache(),
            $this->controleFileAttente(),
            $this->controleJobsEchoues(),
            $this->controleSessions(),
            $this->controleConnexions(),
            $this->controlePlanificateur(),
        ];
    }

    /** Statut global = pire statut parmi les contrôles. */
    public function statutGlobal(?array $controles = null): string
    {
        $controles ??= $this->controles();
        $pire = 'ok';
        foreach ($controles as $c) {
            if (self::RANG[$c['statut']] > self::RANG[$pire]) {
                $pire = $c['statut'];
            }
        }

        return $pire;
    }

    /** Mesures scalaires (pour instantané / historique). */
    public function mesures(): array
    {
        $libre = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        $pct = ($libre && $total) ? round($libre / $total * 100, 2) : null;

        return [
            'disque_libre_pct' => $pct,
            'disque_libre_octets' => $libre ? (int) $libre : null,
            'jobs_en_attente' => $this->compter('jobs'),
            'jobs_echoues' => $this->compter('failed_jobs'),
            'sessions_actives' => $this->compter('sessions'),
            'echecs_connexion_24h' => $this->echecsConnexion24h(),
            'charge_systeme' => function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? null) : null,
        ];
    }

    /** Persiste un instantané de santé (utilisé par la commande planifiée). */
    public function capturer(): SystemMetric
    {
        $controles = $this->controles();
        $mesures = $this->mesures();

        return SystemMetric::create(array_merge($mesures, [
            'capture_le' => now(),
            'statut_global' => $this->statutGlobal($controles),
            'details' => $controles,
            'created_at' => now(),
        ]));
    }

    public function derniereCapture(): ?SystemMetric
    {
        return SystemMetric::orderByDesc('capture_le')->first();
    }

    public function historique(int $limite = 30): Collection
    {
        return SystemMetric::orderByDesc('capture_le')->limit($limite)->get();
    }

    /** Derniers jobs en échec (pour le cockpit). */
    public function jobsEchoues(int $limite = 8): Collection
    {
        if (! Schema::hasTable('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')->orderByDesc('id')->limit($limite)
            ->get(['uuid', 'queue', 'exception', 'failed_at']);
    }

    // ---- Contrôles individuels --------------------------------------------------------------

    private function controleBase(): array
    {
        try {
            DB::select('select 1');
            $statut = 'ok';
            $detail = 'Connexion OK ('.config('database.default').')';
        } catch (Throwable $e) {
            $statut = 'critique';
            $detail = 'Base inaccessible';
        }

        return ['cle' => 'base', 'libelle' => 'Base de données', 'statut' => $statut, 'valeur' => $detail, 'detail' => $detail];
    }

    private function controleDisque(): array
    {
        $m = $this->mesures();
        $pct = $m['disque_libre_pct'];
        $statut = $pct === null ? 'warning' : ($pct < 5 ? 'critique' : ($pct < 15 ? 'warning' : 'ok'));

        return [
            'cle' => 'disque', 'libelle' => 'Espace disque',
            'statut' => $statut,
            'valeur' => $pct === null ? 'n/d' : $pct.'% libre',
            'detail' => $m['disque_libre_octets'] ? round($m['disque_libre_octets'] / 1073741824, 1).' Go libres' : 'n/d',
        ];
    }

    private function controleFileAttente(): array
    {
        $n = $this->tailleFileAttente();
        $statut = $n > 500 ? 'critique' : ($n > 50 ? 'warning' : 'ok');
        $driver = config('queue.default');

        return [
            'cle' => 'file_attente',
            'libelle' => "File d'attente (jobs)",
            'statut' => $statut,
            'valeur' => $n.' en attente',
            'detail' => "Driver : {$driver} · files : ".implode(', ', QueueNames::workerPriorities()),
        ];
    }

    private function controleRedis(): array
    {
        if (! config('performance.redis.health_check_enabled', true)) {
            return [
                'cle' => 'redis', 'libelle' => 'Redis', 'statut' => 'ok',
                'valeur' => 'non vérifié', 'detail' => 'Contrôle désactivé',
            ];
        }

        $requis = app(RedisHealthService::class)->estUtilise();
        // Redis non utilisé (cache et file sur un autre pilote) : pas de tentative de connexion, qui coûtait
        // jusqu'au délai d'expiration (~2 s) à chaque affichage du cockpit.
        if (! $requis && (config('session.driver') !== 'redis')) {
            return [
                'cle' => 'redis', 'libelle' => 'Redis', 'statut' => 'ok',
                'valeur' => 'non utilisé', 'detail' => 'Cache : '.config('cache.default').' · file : '.config('queue.default'),
            ];
        }
        $health = app(RedisHealthService::class)->verifier();

        if ($health['ok']) {
            return [
                'cle' => 'redis', 'libelle' => 'Redis', 'statut' => 'ok',
                'valeur' => ($health['latency_ms'] ?? '?').' ms',
                'detail' => 'Connexion '.$health['connection'].' OK',
            ];
        }

        return [
            'cle' => 'redis', 'libelle' => 'Redis',
            'statut' => $requis ? 'critique' : 'warning',
            'valeur' => 'indisponible',
            'detail' => $health['error'] ?? 'Ping échoué',
        ];
    }

    private function controleCache(): array
    {
        $store = config('cache.default');
        $prod = config('app.env') === 'production';
        $warn = $prod && config('performance.redis.warn_database_in_production', true);

        $statut = 'ok';
        $detail = "Store : {$store}";

        if ($warn && $store === 'database') {
            $statut = 'warning';
            $detail = 'Production : migrer vers redis (CACHE_STORE=redis)';
        }

        return [
            'cle' => 'cache', 'libelle' => 'Cache applicatif',
            'statut' => $statut, 'valeur' => $store, 'detail' => $detail,
        ];
    }

    private function tailleFileAttente(): int
    {
        $driver = config('queue.default');

        if (in_array($driver, ['redis', 'redis_failover'], true)) {
            try {
                return collect(QueueNames::workerPriorities())
                    ->sum(fn (string $q) => (int) Queue::size($q));
            } catch (Throwable) {
                return $this->compter('jobs');
            }
        }

        return $this->compter('jobs');
    }

    private function controleJobsEchoues(): array
    {
        $n = $this->compter('failed_jobs');
        $statut = $n > 20 ? 'critique' : ($n > 0 ? 'warning' : 'ok');

        return ['cle' => 'jobs_echoues', 'libelle' => 'Jobs échoués', 'statut' => $statut, 'valeur' => $n.' échec(s)', 'detail' => $n ? 'À investiguer' : 'Aucun'];
    }

    private function controleSessions(): array
    {
        $n = $this->compter('sessions');

        return ['cle' => 'sessions', 'libelle' => 'Sessions actives', 'statut' => 'ok', 'valeur' => $n.' session(s)', 'detail' => 'Informatif'];
    }

    private function controleConnexions(): array
    {
        $n = $this->echecsConnexion24h();
        $bloquees = Schema::hasTable('blocked_ips')
            ? \App\Models\BlockedIp::actif()->count() : 0;
        $statut = $n > 50 ? 'critique' : ($n >= 10 ? 'warning' : 'ok');

        return ['cle' => 'connexions', 'libelle' => 'Échecs de connexion (24h)', 'statut' => $statut, 'valeur' => $n.' échec(s)', 'detail' => $bloquees.' IP verrouillée(s)'.($n >= 10 ? ' · possible brute-force' : '')];
    }

    private function controlePlanificateur(): array
    {
        $derniere = $this->derniereCapture();
        if ($derniere === null) {
            return ['cle' => 'planificateur', 'libelle' => 'Capture planifiée', 'statut' => 'warning', 'valeur' => 'jamais', 'detail' => 'Activer admin-technique:capturer-sante au scheduler'];
        }
        $heures = $derniere->capture_le->diffInHours(now());
        $statut = $heures > 36 ? 'warning' : 'ok';

        return ['cle' => 'planificateur', 'libelle' => 'Capture planifiée', 'statut' => $statut, 'valeur' => $derniere->capture_le->diffForHumans(), 'detail' => 'Dernier instantané santé'];
    }

    // ---- Internes ---------------------------------------------------------------------------

    /** @var array<string, bool> existence des tables déjà vérifiée par cette instance */
    private array $tables = [];

    private function tableExiste(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }

    private function compter(string $table): int
    {
        return $this->tableExiste($table) ? (int) DB::table($table)->count() : 0;
    }

    private function echecsConnexion24h(): int
    {
        if (! $this->tableExiste('login_logs')) {
            return 0;
        }

        return (int) DB::table('login_logs')->where('succes', false)->where('created_at', '>=', now()->subDay())->count();
    }
}
