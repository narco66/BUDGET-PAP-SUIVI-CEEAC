<?php

namespace App\Support;

use App\Models\ApiAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Journal d'audit des actions MOA interopérabilité (I-P3). */
final class InteropAuditLogger
{
    public static function enregistrer(
        string $action,
        User $user,
        ?string $cibleType = null,
        ?int $cibleId = null,
        array $details = [],
        ?Request $request = null,
    ): void {
        ApiAuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'cible_type' => $cibleType,
            'cible_id' => $cibleId,
            'details' => $details ?: null,
            'ip' => $request?->ip(),
            'created_at' => now(),
        ]);
    }
}
