<?php

namespace App\Support;

use Laravel\Sanctum\TransientToken;

final class SanctumContexte
{
    public static function estSession(?object $token): bool
    {
        return $token === null || $token instanceof TransientToken;
    }

    public static function nomJeton(?object $token): ?string
    {
        if (self::estSession($token) || ! isset($token->name)) {
            return self::estSession($token) ? 'session' : null;
        }

        return is_string($token->name) ? $token->name : null;
    }

    public static function connectedAppId(?object $token): mixed
    {
        if (self::estSession($token) || ! isset($token->connected_app_id)) {
            return null;
        }

        return $token->connected_app_id;
    }
}
