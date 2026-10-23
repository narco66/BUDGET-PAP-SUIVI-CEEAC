<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

/** Publication versionnée d'un circuit D14 (P3). */
class WorkflowVersionService
{
    public function publier(Workflow $workflow, User $user, ?string $libelle = null): WorkflowVersion
    {
        return DB::transaction(function () use ($workflow, $user, $libelle) {
            $workflow->load(['etapes', 'conditions', 'transitions']);
            $numero = (int) $workflow->version + 1;

            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'numero' => $numero,
                'libelle' => $libelle ?? "Version {$numero}",
                'statut' => 'publie',
                'published_at' => now(),
                'created_by' => $user->id,
                'snapshot_json' => [
                    'etapes' => $workflow->etapes->map->only([
                        'ordre', 'libelle', 'role', 'delai_jours',
                        'type_approbation', 'condition_json', 'groupe_parallele',
                    ])->all(),
                    'conditions' => $workflow->conditions->map->only(['code', 'libelle', 'expression'])->all(),
                    'transitions' => $workflow->transitions->map->only([
                        'etape_source_ordre', 'etape_cible_ordre', 'condition_id', 'type', 'priorite',
                    ])->all(),
                ],
            ]);

            $workflow->update([
                'version' => $numero,
                'published_at' => now(),
                'created_by' => $user->id,
            ]);

            return $version;
        });
    }

    public function versionCourante(Workflow $workflow): ?WorkflowVersion
    {
        return $workflow->versions()->where('statut', 'publie')->orderByDesc('numero')->first();
    }
}
