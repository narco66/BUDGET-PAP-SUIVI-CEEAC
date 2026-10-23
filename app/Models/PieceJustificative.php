<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PieceJustificative extends Model
{
    protected $table = 'pieces_justificatives';

    protected $fillable = [
        'attachable_type', 'attachable_id', 'nom', 'chemin',
        'type_mime', 'taille', 'categorie', 'televerse_par',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'televerse_par');
    }
}
