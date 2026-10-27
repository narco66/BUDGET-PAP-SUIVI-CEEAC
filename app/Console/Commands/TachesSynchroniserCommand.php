<?php

namespace App\Console\Commands;

use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Models\WorkflowTache;
use App\Services\Workflow\TacheWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reprise « Mes Tâches » : crée les tâches des circuits en cours (idempotent) et, avec --historique,
 * reconstitue les tâches terminées à partir des décisions déjà enregistrées sur les étapes.
 * N'altère ni les dossiers, ni les instances, ni les étapes.
 */
class TachesSynchroniserCommand extends Command
{
    protected $signature = 'taches:synchroniser {--historique : Reconstituer aussi les tâches terminées des décisions passées}';

    protected $description = 'Crée les tâches « Mes Tâches » des circuits existants (sans modifier les dossiers).';

    public function handle(TacheWorkflowService $taches): int
    {
        $avant = WorkflowTache::count();
        WorkflowInstance::query()->where('statut', 'en_cours')->orderBy('id')->each(function (WorkflowInstance $i) use ($taches) {
            DB::transaction(fn () => $taches->synchroniser($i));
        });
        $ouvertes = WorkflowTache::count() - $avant;

        $historiques = 0;
        if ($this->option('historique')) {
            WorkflowInstanceEtape::query()->with(['instance.workflow', 'instance.objet'])
                ->whereIn('statut', ['valide', 'rejete'])->whereNotNull('acteur_id')
                ->whereDoesntHave('taches')->orderBy('id')
                ->each(function (WorkflowInstanceEtape $e) use ($taches, &$historiques) {
                    if (! $e->instance) {
                        return;
                    }
                    DB::transaction(function () use ($e, $taches) {
                        $t = $taches->reconstituer($e);
                        $taches->journaliser($t, 'decision', $e->acteur, $e->observation, ['decision' => $e->statut, 'reprise' => true]);
                    });
                    $historiques++;
                });
        }

        $this->info("Tâches ouvertes créées : {$ouvertes} ; tâches historiques reconstituées : {$historiques} ; total : ".WorkflowTache::count());

        return self::SUCCESS;
    }
}
