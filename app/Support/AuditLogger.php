<?php

namespace App\Support;

use App\Models\JournalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/** Enregistreur central de la piste d'audit (actions modèle ET actions métier). */
class AuditLogger
{
    /** Champs jamais journalisés. */
    private const MASQUES = ['password', 'remember_token'];

    public static function enregistrer(
        string $action,
        ?Model $auditable = null,
        ?array $avant = null,
        ?array $apres = null,
    ): JournalAudit {
        $user = Auth::user();

        return JournalAudit::create([
            'user_id' => $user?->id,
            'user_nom' => $user?->name,
            'role' => self::roles($user),
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'action' => $action,
            'avant' => self::nettoyer($avant),
            'apres' => self::nettoyer($apres),
            'ip' => Request::ip(),
            'url' => self::url(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    /** Rôle(s) de l'acteur figé(s) au moment de l'action (résiste aux changements d'habilitation). */
    private static function roles(?object $user): ?string
    {
        if ($user === null || ! method_exists($user, 'getRoleNames')) {
            return null;
        }

        $roles = $user->getRoleNames();

        return $roles->isNotEmpty() ? substr($roles->implode(', '), 0, 150) : null;
    }

    private static function nettoyer(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return collect($data)->except(self::MASQUES)->all();
    }

    private static function url(): ?string
    {
        return app()->runningInConsole() ? 'console' : substr((string) Request::fullUrl(), 0, 500);
    }
}
