<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Modèle de rapport construit par l'utilisateur (P3-1). */
class RapportPersonnalise extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'rapports_personnalises';

    protected $fillable = [
        'code', 'titre', 'description', 'source', 'colonnes', 'filtres',
        'cree_par', 'actif', 'partage',
    ];

    protected $casts = [
        'colonnes' => 'array',
        'filtres' => 'array',
        'actif' => 'boolean',
        'partage' => 'boolean',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
