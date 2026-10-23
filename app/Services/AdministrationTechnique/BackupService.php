<?php

namespace App\Services\AdministrationTechnique;

use App\Models\Backup;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Exécution des sauvegardes (Lot T3). Produit un artefact adapté au pilote : `mysqldump` pour MySQL,
 * snapshot logique JSON sinon. Trace chaque exécution (statut, taille, checksum SHA-256, durée).
 */
class BackupService
{
    /** Lance une sauvegarde et retourne l'enregistrement de registre. */
    public function executer(string $type = 'base', string $declenchement = 'manuel', ?int $userId = null): Backup
    {
        $backup = Backup::create([
            'reference' => Numero::suivant('backups', 'BKP', now()->year, 'reference'),
            'type' => $type,
            'declenchement' => $declenchement,
            'statut' => 'en_cours',
            'demarre_le' => now(),
            'declenche_par' => $userId,
        ]);

        $debut = microtime(true);
        try {
            $chemin = $this->produireArtefact($type, $backup->reference);
            $taille = is_file($chemin) ? (int) filesize($chemin) : 0;

            $backup->update([
                'statut' => 'succes',
                'chemin' => $chemin,
                'taille_octets' => $taille,
                'checksum' => is_file($chemin) ? hash_file('sha256', $chemin) : null,
                'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
                'termine_le' => now(),
            ]);
        } catch (Throwable $e) {
            $backup->update([
                'statut' => 'echec',
                'erreur' => substr($e->getMessage(), 0, 2000),
                'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
                'termine_le' => now(),
            ]);
        }

        return $backup->fresh();
    }

    /** Purge les sauvegardes au-delà de la rétention (fichier + enregistrement). */
    public function purger(int $retentionJours): int
    {
        $n = 0;
        foreach (Backup::where('created_at', '<', now()->subDays($retentionJours))->get() as $b) {
            if ($b->chemin && is_file($b->chemin)) {
                @unlink($b->chemin);
            }
            $b->delete();
            $n++;
        }

        return $n;
    }

    private function produireArtefact(string $type, string $reference): string
    {
        $dossier = storage_path('app/backups');
        File::ensureDirectoryExists($dossier);
        $driver = config('database.default');

        if ($type === 'base' && $driver === 'mysql') {
            return $this->dumpMysql($dossier, $reference);
        }

        // Pilote non-MySQL ou type fichiers : snapshot logique (manifeste des tables + volumétrie).
        return $this->snapshotLogique($dossier, $reference, $driver);
    }

    private function dumpMysql(string $dossier, string $reference): string
    {
        $c = config('database.connections.mysql');
        $chemin = "{$dossier}/{$reference}.sql";

        $process = new Process([
            'mysqldump', '--host='.$c['host'], '--port='.$c['port'],
            '--user='.$c['username'], '--password='.$c['password'],
            '--single-transaction', '--routines', $c['database'],
        ]);
        $process->setTimeout(600);
        $process->run(fn ($type, $buffer) => file_put_contents($chemin, $buffer, FILE_APPEND));

        if (! $process->isSuccessful()) {
            @unlink($chemin);
            throw new \RuntimeException('mysqldump: '.$process->getErrorOutput());
        }

        return $chemin;
    }

    /** Snapshot logique JSON (manifeste) — utilisé hors MySQL et pour les fichiers/tests. */
    private function snapshotLogique(string $dossier, string $reference, string $driver): string
    {
        $chemin = "{$dossier}/{$reference}.json";
        $tables = collect(DB::select(
            $driver === 'sqlite'
                ? "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
                : 'SHOW TABLES'
        ))->map(fn ($r) => array_values((array) $r)[0]);

        $manifeste = [
            'reference' => $reference,
            'driver' => $driver,
            'genere_le' => now()->toIso8601String(),
            'tables' => $tables->mapWithKeys(function ($t) {
                try {
                    return [$t => (int) DB::table($t)->count()];
                } catch (Throwable) {
                    return [$t => null];
                }
            })->all(),
        ];

        File::put($chemin, json_encode($manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $chemin;
    }
}
