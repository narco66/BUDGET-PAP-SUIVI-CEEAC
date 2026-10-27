<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Versions publiées d'un circuit (référentiel Workflow § 7, P06) : chaque publication est un instantané immuable
 * de la définition ; une instance reste sur la version avec laquelle elle a démarré (ses étapes sont copiées).
 */
class WorkflowVersionService
{
    public function publier(Workflow $workflow, ?User $user, ?string $libelle = null): WorkflowVersion
    {
        return DB::transaction(function () use ($workflow, $user, $libelle) {
            $workflow->load(['etapes', 'conditions', 'transitions']);
            $precedente = $this->versionCourante($workflow);
            $numero = max((int) $workflow->versions()->max('numero'), $precedente ? (int) $workflow->version : 0) + 1;
            $snapshot = $this->instantane($workflow);

            // Les versions antérieures restent consultables mais ne s'appliquent plus aux nouveaux dossiers.
            $workflow->versions()->where('statut', 'publie')->update(['statut' => 'retire']);
            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'numero' => $numero,
                'libelle' => $libelle ?? "Version {$numero}",
                'statut' => 'publie',
                'published_at' => now(),
                'created_by' => $user?->id,
                'snapshot_json' => $snapshot,
            ]);

            $workflow->update([
                'version' => $numero,
                'published_at' => now(),
                'created_by' => $user?->id ?? $workflow->created_by,
            ]);
            AuditLogger::evenement('workflow_version_publiee', $workflow, [
                'module' => 'WORKFLOW',
                'reference' => $workflow->code.' v'.$numero,
                'avant' => $precedente ? ['version' => $precedente->numero, 'etapes' => $precedente->snapshot_json['etapes'] ?? []] : null,
                'apres' => ['version' => $numero, 'etapes' => $snapshot['etapes'], 'empreinte' => hash('sha256', json_encode($snapshot))],
            ]);

            return $version;
        });
    }

    public function versionCourante(Workflow $workflow): ?WorkflowVersion
    {
        return $workflow->versions()->where('statut', 'publie')->orderByDesc('numero')->first();
    }

    /** Version applicable au démarrage d'un dossier ; la version initiale est publiée si aucune ne l'est encore. */
    public function assurerVersion(Workflow $workflow, ?User $user = null): WorkflowVersion
    {
        return $this->versionCourante($workflow)
            ?? $this->publier($workflow, $user, 'Version initiale (reprise de la définition en vigueur)');
    }

    /** @return array<string, mixed> */
    private function instantane(Workflow $workflow): array
    {
        return [
            'etapes' => $workflow->etapes->sortBy('ordre')->map->only([
                'ordre', 'libelle', 'role', 'delai_jours',
                'type_approbation', 'condition_json', 'groupe_parallele',
            ])->values()->all(),
            'conditions' => $workflow->conditions->map->only(['code', 'libelle', 'expression'])->all(),
            'transitions' => $workflow->transitions->map->only([
                'etape_source_ordre', 'etape_cible_ordre', 'condition_id', 'type', 'priorite',
            ])->all(),
        ];
    }
}
