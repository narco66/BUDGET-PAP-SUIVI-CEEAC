<?php

namespace App\Console\Commands;

use App\Services\Audit\StructureMasterDataAuditService;
use Illuminate\Console\Command;

class AlignerStructuresOfficiellesCommand extends Command
{
    protected $signature = 'institution:align-structures
                            {--apply : Appliquer les corrections; sinon simulation}
                            {--json : Sortie JSON}';

    protected $description = 'Aligne les rattachements structure_id sur les structures officielles du referentiel Structures';

    public function handle(StructureMasterDataAuditService $audit): int
    {
        $rapport = $audit->appliquerAliasOfficiels(! $this->option('apply'));

        if ($this->option('json')) {
            $this->line(json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $mode = $rapport['dry_run'] ? 'simulation' : 'application';
        $this->info("Alignement des structures officielles ({$mode})");
        $this->line('Total concerne : '.$rapport['total'].' ligne(s)');

        foreach ($rapport['tables'] as $table) {
            $this->line("  - {$table['table']} : {$table['lignes']} ligne(s)");
        }

        if ($rapport['dry_run']) {
            $this->comment('Relancez avec --apply pour appliquer les remplacements alias -> structure officielle.');
        }

        return self::SUCCESS;
    }
}
