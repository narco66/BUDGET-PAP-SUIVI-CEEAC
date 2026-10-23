<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Identifiants OAuth applicatifs rattachés à une connected_app (I-P1). */
class ApiClient extends Model
{
    protected $fillable = [
        'connected_app_id', 'client_id', 'client_secret_hash', 'libelle',
        'statut', 'revoked_at', 'revoked_by', 'derniere_utilisation_le',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'derniere_utilisation_le' => 'datetime',
    ];

    public function connectedApp(): BelongsTo
    {
        return $this->belongsTo(ConnectedApp::class);
    }

    public function revoquePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function estActif(): bool
    {
        return $this->statut === 'actif' && $this->revoked_at === null;
    }
}
