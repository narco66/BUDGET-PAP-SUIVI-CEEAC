<?php

namespace App\Services\AdministrationTechnique;

use App\Support\QueueNames;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Monitoring des files d'attente (P3-5) — alternative légère à Horizon, 100 % natif Laravel.
 */
class QueueMonitorService
{
    /** Synthèse globale pour le tableau de bord. */
    public function resume(): array
    {
        $driver = (string) config('queue.default');

        return [
            'driver' => $driver,
            'total_en_attente' => $this->totalEnAttente(),
            'total_echoues' => $this->compter('failed_jobs'),
            'lots_actifs' => Schema::hasTable('job_batches')
                ? (int) DB::table('job_batches')->whereNull('finished_at')->whereNull('cancelled_at')->count()
                : 0,
            'commande_worker' => $this->commandeWorker(),
            'peut_lister_jobs' => in_array($driver, ['database', 'sync'], true),
        ];
    }

    /** @return array<string, int> file => nombre de jobs en attente */
    public function filesParQueue(): array
    {
        $connues = QueueNames::workerPriorities();
        $resultat = [];

        foreach ($connues as $file) {
            $resultat[$file] = $this->tailleFile($file);
        }

        if (! Schema::hasTable('jobs')) {
            return $resultat;
        }

        $autres = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as total'))
            ->whereNotIn('queue', $connues)
            ->groupBy('queue')
            ->pluck('total', 'queue');

        foreach ($autres as $file => $total) {
            $resultat[(string) $file] = (int) $total;
        }

        return $resultat;
    }

    /** Jobs en attente (driver database uniquement). */
    public function jobsEnAttente(int $limite = 20): Collection
    {
        if (! Schema::hasTable('jobs') || ! in_array(config('queue.default'), ['database', 'sync'], true)) {
            return collect();
        }

        return DB::table('jobs')
            ->orderByDesc('id')
            ->limit($limite)
            ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->map(fn ($j) => $this->enrichirJobEnAttente($j));
    }

    public function jobsEchoues(?string $file = null, int $parPage = 20): LengthAwarePaginator
    {
        if (! Schema::hasTable('failed_jobs')) {
            return new Paginator([], 0, $parPage, 1, ['path' => request()->url(), 'query' => request()->query()]);
        }

        $query = DB::table('failed_jobs')->orderByDesc('id');

        if ($file !== null && $file !== '') {
            $query->where('queue', $file);
        }

        return $query->paginate($parPage, ['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->through(fn ($j) => $this->enrichirJobEchoue($j));
    }

    public function detailEchec(string $uuid): ?object
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        if ($job === null) {
            return null;
        }

        return $this->enrichirJobEchoue($job);
    }

    public function relancer(string $uuid): bool
    {
        if (app('queue.failer')->find($uuid) === null) {
            return false;
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return true;
    }

    /** @return int Nombre de jobs relancés */
    public function relancerTout(): int
    {
        $ids = collect(app('queue.failer')->all())->pluck('uuid')->filter()->values()->all();
        if ($ids === []) {
            return 0;
        }

        Artisan::call('queue:retry', ['id' => $ids]);

        return count($ids);
    }

    public function supprimer(string $uuid): bool
    {
        return app('queue.failer')->forget($uuid);
    }

    public function purgerEchecs(): void
    {
        Artisan::call('queue:flush');
    }

    /** Lots (batches) récents. */
    public function lotsRecents(int $limite = 10): Collection
    {
        if (! Schema::hasTable('job_batches')) {
            return collect();
        }

        return DB::table('job_batches')
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get()
            ->map(function ($lot) {
                $lot->termine = $lot->finished_at !== null;
                $lot->annule = $lot->cancelled_at !== null;

                return $lot;
            });
    }

    public function commandeWorker(): string
    {
        $driver = config('queue.default');
        $files = implode(',', QueueNames::workerPriorities());

        return "php artisan queue:work {$driver} --queue={$files} --tries=3 --timeout=600";
    }

    /** @return list<string> */
    public function filesConnues(): array
    {
        return QueueNames::workerPriorities();
    }

    // ---- Internes ---------------------------------------------------------------------------

    private function totalEnAttente(): int
    {
        return array_sum($this->filesParQueue());
    }

    private function tailleFile(string $file): int
    {
        $driver = config('queue.default');

        if (in_array($driver, ['redis', 'redis_failover'], true)) {
            try {
                return (int) Queue::size($file);
            } catch (Throwable) {
                return Schema::hasTable('jobs')
                    ? (int) DB::table('jobs')->where('queue', $file)->count()
                    : 0;
            }
        }

        return Schema::hasTable('jobs')
            ? (int) DB::table('jobs')->where('queue', $file)->count()
            : 0;
    }

    private function compter(string $table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }

    private function enrichirJobEnAttente(object $job): object
    {
        $payload = json_decode($job->payload, true) ?? [];
        $job->nom = $payload['displayName'] ?? class_basename($payload['job'] ?? 'Job');
        $job->reserve = $job->reserved_at !== null;
        $job->cree_le = $job->created_at ? date('Y-m-d H:i:s', (int) $job->created_at) : null;
        $job->disponible_le = $job->available_at ? date('Y-m-d H:i:s', (int) $job->available_at) : null;

        return $job;
    }

    private function enrichirJobEchoue(object $job): object
    {
        $payload = json_decode($job->payload, true) ?? [];
        $job->nom = $payload['displayName'] ?? class_basename($payload['job'] ?? 'Job');
        $job->exception_courte = Str::limit((string) ($job->exception ?? ''), 120);
        $job->exception_lignes = Str::limit((string) ($job->exception ?? ''), 8000);

        return $job;
    }
}
