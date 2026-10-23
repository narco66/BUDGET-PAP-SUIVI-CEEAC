<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alerte extends Model
{
    protected $fillable = [
        'reference', 'module', 'code', 'criticite', 'titre', 'message',
        'exercice_id', 'structure_id', 'objet_type', 'objet_id', 'statut',
        'assigne_a', 'resolu_par', 'resolu_le', 'resolu_commentaire',
        'escalade_niveau', 'derniere_escalade_le',
        'metadata', 'detecte_le',
    ];

    protected $casts = [
        'metadata' => 'array',
        'detecte_le' => 'datetime',
        'resolu_le' => 'datetime',
        'derniere_escalade_le' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function objet(): MorphTo
    {
        return $this->morphTo();
    }

    public function assigne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }

    public function resolveur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolu_par');
    }

    public function estOuverte(): bool
    {
        return $this->statut === 'ouverte';
    }
}
