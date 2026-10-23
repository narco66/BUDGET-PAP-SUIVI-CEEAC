<?php

namespace App\Services\Audit;

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
        $query = DB::table($table)->where('created_at', '<', $seuil);
        $eligibles = (int) $query->count();

        if ($dryRun || $eligibles === 0) {
            return ['jours' => $jours, 'supprimes' => 0, 'eligibles' => $eligibles];
        }

        $batch = max(100, (int) config('audit.purge.batch_size', 5000));
        $supprimes = 0;

        do {
            $lot = DB::table($table)
                ->where('created_at', '<', $seuil)
                ->orderBy('created_at')
                ->limit($batch)
                ->delete();
            $supprimes += $lot;
        } while ($lot === $batch);

        return ['jours' => $jours, 'supprimes' => $supprimes, 'eligibles' => $eligibles];
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
