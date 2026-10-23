<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\WorkflowService;
use App\Support\WorkflowObjetUrls;
use Illuminate\Support\Collection;

/** Corbeille transverse des validations en attente (P1). */
class WorkflowCorbeilleService
{
    public function __construct(
        private readonly WorkflowService $workflow,
    ) {
    }

    /** @return Collection<int, array{instance: WorkflowInstance, etape: mixed, objet: mixed, url: ?string, libelle: string, circuit: string, en_retard: bool}> */
    public function tachesPour(User $user): Collection
    {
        return WorkflowInstance::query()
            ->with(['workflow', 'objet', 'etapes'])
            ->where('statut', 'en_cours')
            ->latest('updated_at')
            ->get()
            ->filter(fn (WorkflowInstance $i) => $this->workflow->peutAgir($i, $user))
            ->map(function (WorkflowInstance $instance) {
                $etape = $instance->etapeCourante();

                return [
                    'instance' => $instance,
                    'etape' => $etape,
                    'objet' => $instance->objet,
                    'url' => WorkflowObjetUrls::show($instance->objet),
                    'libelle' => WorkflowObjetUrls::libelle($instance->objet),
                    'circuit' => WorkflowObjetUrls::typeLibelle($instance),
                    'en_retard' => $etape?->echeance_le && $etape->echeance_le->isPast(),
                ];
            })
            ->values();
    }

    public function compterPour(User $user): int
    {
        return $this->tachesPour($user)->count();
    }
}
