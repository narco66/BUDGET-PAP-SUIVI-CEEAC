<?php

namespace App\Services\Workflow;

use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use Illuminate\Support\Facades\DB;

/** Relances SLA sur étapes workflow en retard (P1). */
class WorkflowRelanceService
{
    public function __construct(
        private readonly WorkflowNotificationService $notifications,
        private readonly WorkflowAuditService $audit,
    ) {
    }

    /** @return int Nombre de relances envoyées */
    public function traiter(): int
    {
        $intervalle = (int) config('workflow.relance.intervalle_jours', 3);
        $count = 0;

        $etapes = WorkflowInstanceEtape::query()
            ->whereHas('instance', fn ($q) => $q->where('statut', 'en_cours'))
            ->where('statut', 'en_attente')
            ->whereNotNull('echeance_le')
            ->where('echeance_le', '<', now())
            ->where(function ($q) use ($intervalle) {
                $q->whereNull('derniere_relance_le')
                    ->orWhere('derniere_relance_le', '<', now()->subDays($intervalle));
            })
            ->with(['instance.workflow', 'instance.objet'])
            ->get()
            ->filter(fn (WorkflowInstanceEtape $e) => (int) $e->instance?->etape_courante === (int) $e->ordre);

        foreach ($etapes as $etapeInstance) {
            $instance = $etapeInstance->instance;
            if (! $instance) {
                continue;
            }

            $joursRetard = (int) $etapeInstance->echeance_le->diffInDays(now());

            DB::transaction(function () use ($etapeInstance, $instance, $joursRetard, &$count) {
                $this->notifications->notifierRelance($instance, max(1, $joursRetard));
                $etapeInstance->update(['derniere_relance_le' => now()]);
                $this->audit->enregistrer(
                    'workflow_relance',
                    $instance,
                    null,
                    $etapeInstance->ordre,
                    "Relance automatique — échéance dépassée.",
                    null,
                    ['jours_retard' => $joursRetard],
                );
                $count++;
            });
        }

        return $count;
    }
}
