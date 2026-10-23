<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\VisaElectronique;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use Illuminate\Support\Facades\Request;

/** Visa électronique unifié lié aux étapes D14 (P2). */
class VisaElectroniqueService
{
    public function emettre(
        WorkflowInstance $instance,
        WorkflowInstanceEtape $etape,
        User $acteur,
        string $typeVisa,
        ?string $observation = null,
    ): VisaElectronique {
        $payload = implode('|', [
            $instance->id,
            $etape->ordre,
            $etape->role,
            $typeVisa,
            $acteur->id,
            $observation ?? '',
            now()->toIso8601String(),
        ]);

        $hash = hash('sha256', $payload);
        $certificat = base64_encode(json_encode([
            'instance_id' => $instance->id,
            'etape' => $etape->ordre,
            'role' => $etape->role,
            'type' => $typeVisa,
            'acteur' => $acteur->only(['id', 'name', 'email']),
            'hash' => $hash,
            'horodatage' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE));

        $visa = VisaElectronique::create([
            'workflow_instance_id' => $instance->id,
            'workflow_instance_etape_id' => $etape->id,
            'type_visa' => $typeVisa,
            'acteur_id' => $acteur->id,
            'hash_contenu' => $hash,
            'certificat' => $certificat,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        $etape->update([
            'hash_visa' => $hash,
            'ip' => $visa->ip,
            'user_agent' => $visa->user_agent,
        ]);

        return $visa;
    }
}
