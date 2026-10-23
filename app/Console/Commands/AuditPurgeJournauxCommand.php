<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditRetentionService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Purge les journaux d'audit au-delà du délai de rétention (P2-4). */
class AuditPurgeJournauxCommand extends Command
{
    protected $signature = 'audit:purge-journaux
                            {--table= : Table ciblée (défaut : toutes)}
                            {--jours= : Délai en jours (défaut : config par table)}
                            {--dry-run : Simuler sans supprimer}';

    protected $description = 'Purge les journaux d\'audit plus anciens que la rétention configurée';

    public function handle(AuditRetentionService $retention): int
    {
        if (! config('audit.purge.enabled', true)) {
            $this->warn('Purge désactivée (AUDIT_PURGE_ENABLED=false).');

            return self::SUCCESS;
        }

        $table = $this->option('table') ?: null;
        $jours = $this->option('jours') !== null ? (int) $this->option('jours') : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $resultats = $retention->purger($table, $jours, $dryRun);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($resultats === []) {
            $this->info('Aucune table d\'audit éligible.');

            return self::SUCCESS;
        }

        $lignes = [];
        foreach ($resultats as $nom => $stats) {
            $lignes[] = [
                $nom,
                $stats['jours'],
                $stats['eligibles'],
                $dryRun ? 0 : $stats['supprimes'],
            ];
        }

        $this->table(['Table', 'Rétention (j)', 'Éligibles', $dryRun ? 'Simulé' : 'Supprimés'], $lignes);

        if ($dryRun) {
            $this->comment('Mode simulation — aucune suppression effectuée.');
        }

        return self::SUCCESS;
    }
}
