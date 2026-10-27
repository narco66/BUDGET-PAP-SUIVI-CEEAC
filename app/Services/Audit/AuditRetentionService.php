<?php

namespace App\Services\Audit;

use App\Models\AuditArchive;
use App\Support\AuditLogger;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Purge des journaux d'audit au-delà des délais de rétention configurés. */
class AuditRetentionService
{
    /** @return array<string, array{jours: int, supprimes: int, eligibles: int}> */
    public function purger(?string $table = null, ?int $jours = null, bool $dryRun = false): array
    {
        $tables = $table !== null ? [$table => $this->joursPour($table, $jours)] : $this->tablesConfigurées($jours);
        $resultats = [];

        foreach ($tables as $nom => $delai) {
            $resultats[$nom] = $this->purgerTable($nom, $delai, $dryRun);
        }

        return $resultats;
    }

    /** @return array<string, int> */
    public function tablesConfigurées(?int $joursOverride = null): array
    {
        $tables = [];

        foreach (config('audit.retention', []) as $table => $jours) {
            if (Schema::hasTable($table)) {
                $tables[$table] = $this->joursPour($table, $joursOverride ?? (int) $jours);
            }
        }

        return $tables;
    }

    private function purgerTable(string $table, int $jours, bool $dryRun): array
    {
        $seuil = now()->subDays($jours);
        $query = fn () => $this->restreindreAuxArchives($table, DB::table($table)->where('created_at', '<', $seuil));
        $eligibles = (int) $query()->count();

        if ($dryRun || $eligibles === 0) {
            return ['jours' => $jours, 'supprimes' => 0, 'eligibles' => $eligibles];
        }

        $batch = max(100, (int) config('audit.purge.batch_size', 5000));

        // Les journaux sont en ajout seul (déclencheurs PostgreSQL) : la purge réglementée lève la protection
        // pour sa seule transaction, puis se journalise elle-même (§ 13, JNL-020).
        $supprimes = DB::transaction(function () use ($table, $query, $batch, $seuil, $jours, $eligibles) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL gesbudep.audit_maintenance = 'on'");
            }
            $supprimes = 0;
            do {
                $ids = $query()->orderBy('id')->limit($batch)->pluck('id');
                $lot = $ids->isEmpty() ? 0 : DB::table($table)->whereIn('id', $ids)->delete();
                $supprimes += $lot;
            } while ($lot === $batch);
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL gesbudep.audit_maintenance = 'off'");
            }

            AuditLogger::evenement('audit_purge', null, [
                'gravite' => 'eleve',
                'motif' => "Purge réglementée de {$table} au-delà de {$jours} jours",
                'apres' => ['table' => $table, 'avant_le' => $seuil->toIso8601String(), 'eligibles' => $eligibles, 'supprimes' => $supprimes],
            ]);

            return $supprimes;
        });

        return ['jours' => $jours, 'supprimes' => $supprimes, 'eligibles' => $eligibles];
    }

    /** Le journal central ne se purge que sur des plages déjà archivées hors base et vérifiées (JNL-019). */
    private function restreindreAuxArchives(string $table, Builder $query): Builder
    {
        if ($table !== 'journaux_audit') {
            return $query;
        }
        $plages = AuditArchive::query()->where('table_source', $table)->get(['de_id', 'a_id']);
        if ($plages->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($plages) {
            foreach ($plages as $p) {
                $q->orWhereBetween('id', [$p->de_id, $p->a_id]);
            }
        });
    }

    private function joursPour(string $table, ?int $jours): int
    {
        if (! Schema::hasTable($table)) {
            throw new InvalidArgumentException("Table d'audit introuvable : {$table}.");
        }

        if ($jours === null) {
            if (! array_key_exists($table, config('audit.retention', []))) {
                throw new InvalidArgumentException("Aucune rétention configurée pour « {$table} » — utilisez --jours.");
            }
            $delai = (int) config("audit.retention.{$table}");
        } else {
            $delai = $jours;
        }

        $minimum = (int) config('audit.purge.min_jours', 90);

        if ($delai < $minimum) {
            throw new InvalidArgumentException(
                "Rétention {$delai} j pour « {$table} » inférieure au minimum ({$minimum} j)."
            );
        }

        return $delai;
    }
}
