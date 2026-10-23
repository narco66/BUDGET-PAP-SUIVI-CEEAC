<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/** Jeton Sanctum enrichi avec traçabilité application connectée (I-P1). */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name', 'token', 'abilities', 'connected_app_id', 'api_client_id',
        'expires_at', 'last_used_at',
    ];

    public function connectedApp()
    {
        return $this->belongsTo(ConnectedApp::class);
    }

    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }
}
