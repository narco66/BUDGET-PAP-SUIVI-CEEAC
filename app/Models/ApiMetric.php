<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiMetric extends Model
{
    protected $fillable = [
        'jour', 'chemin', 'connected_app_id', 'appels_total', 'appels_succes',
        'appels_erreur', 'appels_rejet', 'latence_moy_ms', 'latence_max_ms',
    ];

    protected $casts = ['jour' => 'date'];

    public function connectedApp(): BelongsTo
    {
        return $this->belongsTo(ConnectedApp::class);
    }
}
