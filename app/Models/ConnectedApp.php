<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Application tierce enregistrée dans le portail interopérabilité (I-P1). */
class ConnectedApp extends Model
{
    protected $fillable = [
        'code', 'libelle', 'description', 'type', 'contact_email',
        'scopes_autorises', 'statut', 'created_by',
    ];

    protected $casts = [
        'scopes_autorises' => 'array',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(ApiClient::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estActive(): bool
    {
        return $this->statut === 'actif';
    }

    public function estSuspendue(): bool
    {
        return $this->statut === 'suspendu';
    }

    public function estRevokee(): bool
    {
        return $this->statut === 'revoque';
    }
}
