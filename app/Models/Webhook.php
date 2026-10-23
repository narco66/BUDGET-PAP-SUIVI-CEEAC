<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    protected $fillable = [
        'connected_app_id', 'libelle', 'url', 'methode', 'evenement', 'secret', 'actif',
    ];

    protected $casts = ['actif' => 'boolean'];

    public function livraisons(): HasMany
    {
        return $this->hasMany(WebhookLivraison::class);
    }

    public function connectedApp(): BelongsTo
    {
        return $this->belongsTo(ConnectedApp::class);
    }

    public function scopePour($q, string $evenement)
    {
        return $q->where('evenement', $evenement)->where('actif', true);
    }
}
