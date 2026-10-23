<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementRejet extends Model
{
    protected $table = 'paiement_rejets';

    protected $fillable = [
        'paiement_id', 'motif', 'observation', 'rejete_par', 'rejete_le',
    ];

    protected $casts = [
        'rejete_le' => 'datetime',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejete_par');
    }
}
