<?php

namespace App\Services\Reporting;

use App\Models\AuditReporting;
use App\Models\Rapport;
use App\Models\User;
use Illuminate\Http\Request;

/** Journal d'audit des actions sur le module Reporting (P3). */
class RapportAuditService
{
    /** @param array<string, mixed> $metadonnees */
    public function journaliser(
        string $action,
        ?Rapport $rapport = null,
        ?User $user = null,
        ?Request $request = null,
        array $metadonnees = [],
    ): AuditReporting {
        return AuditReporting::create([
            'rapport_id' => $rapport?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'type_rapport' => $rapport?->type ?? ($metadonnees['type'] ?? null),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadonnees' => $metadonnees ?: null,
            'created_at' => now(),
        ]);
    }
}
