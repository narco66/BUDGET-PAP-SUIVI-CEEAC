<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueAffectation extends Model
{
    protected $table = 'historiques_affectations';

    protected $fillable = [
        'user_id', 'structure_id', 'date_debut', 'date_fin', 'principal', 'modifie_par',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'principal' => 'boolean',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }

    public function scopeOuvertes($query)
    {
        return $query->whereNull('date_fin');
    }
}
