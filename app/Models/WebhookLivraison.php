<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLivraison extends Model
{
    public $timestamps = false;

    protected $table = 'webhook_livraisons';

    protected $fillable = [
        'webhook_id', 'evenement', 'statut', 'payload', 'statut_http', 'succes',
        'tentatives', 'prochaine_tentative_le', 'erreur', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'succes' => 'boolean',
        'prochaine_tentative_le' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
