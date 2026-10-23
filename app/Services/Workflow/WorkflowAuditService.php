<?php

namespace App\Services\Workflow;

use App\Models\AuditWorkflow;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/** Journal MOA workflow (P1) — complète la piste d'audit globale. */
class WorkflowAuditService
{
    public function enregistrer(
        string $action,
        WorkflowInstance $instance,
        ?User $acteur = null,
        ?int $etapeOrdre = null,
        ?string $observation = null,
        ?string $categorieRejet = null,
        ?array $meta = null,
    ): AuditWorkflow {
        $objet = $instance->relationLoaded('objet') ? $instance->objet : $instance->objet()->first();

        AuditLogger::enregistrer($action, $objet, null, array_filter([
            'workflow' => $instance->workflow?->code,
            'instance_id' => $instance->id,
            'etape' => $etapeOrdre,
            'observation' => $observation,
            'categorie_rejet' => $categorieRejet,
        ]));

        return AuditWorkflow::create([
            'workflow_instance_id' => $instance->id,
            'workflow_code' => $instance->workflow?->code,
            'objet_type' => $objet ? $objet::class : null,
            'objet_id' => $objet?->getKey(),
            'action' => $action,
            'etape_ordre' => $etapeOrdre,
            'acteur_id' => $acteur?->id,
            'observation' => $observation,
            'categorie_rejet' => $categorieRejet,
            'meta' => $meta,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    public function enregistrerDemarrage(WorkflowInstance $instance, Model $objet, User $user): AuditWorkflow
    {
        AuditLogger::enregistrer('workflow_demarre', $objet, null, [
            'workflow' => $instance->workflow?->code,
            'instance_id' => $instance->id,
        ]);

        return $this->enregistrer('workflow_demarre', $instance, $user, $instance->etape_courante);
    }
}
