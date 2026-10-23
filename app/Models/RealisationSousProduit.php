<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Snapshot consolidation sous-produit (phase S3). */
class RealisationSousProduit extends Model
{
    protected $fillable = [
        'campagne_id', 'sous_produit_id', 'taux_physique', 'taux_financier',
        'nb_activites', 'nb_collectes_validees',
    ];

    protected $casts = [
        'taux_physique' => 'decimal:2',
        'taux_financier' => 'decimal:2',
    ];

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneSuivi::class, 'campagne_id');
    }

    public function sousProduit(): BelongsTo
    {
        return $this->belongsTo(SousProduit::class);
    }
}
