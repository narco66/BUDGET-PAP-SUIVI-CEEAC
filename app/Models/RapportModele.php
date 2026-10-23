<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapportModele extends Model
{
    protected $fillable = ['categorie_id', 'type', 'titre', 'description', 'parametres_schema', 'actif'];

    protected $casts = ['parametres_schema' => 'array', 'actif' => 'boolean'];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(RapportCategorie::class, 'categorie_id');
    }

    public function rapports(): HasMany
    {
        return $this->hasMany(Rapport::class, 'modele_id');
    }
}
