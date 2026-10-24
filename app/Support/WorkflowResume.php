<?php

namespace App\Support;

use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Model;

final class WorkflowResume
{
    /** @return array<string, mixed>|null */
    public static function api(Model $objet, ?User $user): ?array
    {
        $instance = $objet->workflowActif;
        if ($instance === null) {
            return null;
        }

        $workflow = app(WorkflowService::class);
        $instance->loadMissing('workflow');

        return [
            'id' => $instance->id,
            'code' => $instance->workflow?->code,
            'statut' => $instance->statut,
            'etape_courante' => $instance->etape_courante,
            'peut_agir' => $user !== null && $workflow->peutAgir($instance, $user),
        ];
    }
}
