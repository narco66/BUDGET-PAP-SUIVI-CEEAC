<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    public $timestamps = false;

    protected $table = 'api_logs';

    protected $fillable = [
        'user_id', 'token_nom', 'connected_app_id', 'methode', 'chemin', 'request_hash',
        'statut', 'statut_metier', 'duree_ms', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connectedApp(): BelongsTo
    {
        return $this->belongsTo(ConnectedApp::class);
    }
}
