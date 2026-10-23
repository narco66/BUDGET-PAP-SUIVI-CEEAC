<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Model;

/** Résout la prochaine étape via le graphe de transitions (P3). */
class WorkflowTransitionResolver
{
    public function __construct(
        private readonly WorkflowConditionEvaluator $conditions,
    ) {}

    public function prochainOrdre(WorkflowInstance $instance, int $ordreCourant, ?Model $objet): ?int
    {
        $workflow = $instance->relationLoaded('workflow') ? $instance->workflow : $instance->workflow()->first();
        if (! $workflow) {
            return $this->ordreSuivantParDefaut($instance, $ordreCourant);
        }

        $transitions = WorkflowTransition::query()
            ->where('workflow_id', $workflow->id)
            ->when($instance->workflow_version_id, fn ($q) => $q->where(function ($q) use ($instance) {
                $q->whereNull('workflow_version_id')
                    ->orWhere('workflow_version_id', $instance->workflow_version_id);
            }))
            ->where('etape_source_ordre', $ordreCourant)
            ->orderByDesc('priorite')
            ->with('condition')
            ->get();

        foreach ($transitions as $transition) {
            if ($this->conditions->satisfait($transition->condition, $objet)) {
                if ($transition->etape_cible_ordre === null) {
                    return null;
                }

                return (int) $transition->etape_cible_ordre;
            }
        }

        return $this->ordreSuivantParDefaut($instance, $ordreCourant);
    }

    private function ordreSuivantParDefaut(WorkflowInstance $instance, int $ordreCourant): ?int
    {
        $next = $instance->etapes()->where('ordre', '>', $ordreCourant)->min('ordre');

        return $next !== null ? (int) $next : null;
    }
}
