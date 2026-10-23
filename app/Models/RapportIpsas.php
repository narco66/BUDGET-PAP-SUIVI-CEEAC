<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapportIpsas extends Model
{
    protected $table = 'rapports_ipsas';

    protected $fillable = ['rapport_id', 'exercice_id', 'reference_note', 'donnees_preparatoires'];

    protected $casts = ['donnees_preparatoires' => 'array'];

    public function rapport(): BelongsTo
    {
        return $this->belongsTo(Rapport::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
