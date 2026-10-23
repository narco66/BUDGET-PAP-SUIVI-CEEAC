<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapportCategorie extends Model
{
    protected $fillable = ['code', 'libelle', 'ordre', 'actif'];

    protected $casts = ['actif' => 'boolean', 'ordre' => 'integer'];

    public function modeles(): HasMany
    {
        return $this->hasMany(RapportModele::class, 'categorie_id');
    }
}
