<?php

namespace App\Console\Commands;

use App\Models\Workflow;
use App\Services\Workflow\WorkflowVersionService;
use Illuminate\Console\Command;

/**
 * Publie la version initiale (instantané de la définition en vigueur) des circuits qui n'en ont aucune.
 * Les instances déjà démarrées ne sont pas rattachées rétroactivement : leurs étapes copiées font foi. Idempotent.
 */
class WorkflowVersionsInitialesCommand extends Command
{
    protected $signature = 'workflow:versions-initiales';

    protected $description = 'Publie la version initiale des circuits sans version publiée';

    public function handle(WorkflowVersionService $versions): int
    {
        $publiees = 0;
        foreach (Workflow::query()->orderBy('code')->get() as $workflow) {
            if ($versions->versionCourante($workflow)) {
                continue;
            }
            $v = $versions->publier($workflow, null, 'Version initiale (reprise de la définition en vigueur)');
            $this->line("{$workflow->code} : version {$v->numero} publiée ({$workflow->etapes()->count()} étape(s)).");
            $publiees++;
        }
        $this->info("Versions initiales publiées : {$publiees}.");

        return self::SUCCESS;
    }
}
