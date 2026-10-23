<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtatMembre extends Model
{
    protected $table = 'etats_membres';

    protected $fillable = ['code', 'pays', 'cle_repartition', 'actif'];

    protected $casts = ['actif' => 'boolean', 'cle_repartition' => 'decimal:4'];

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
