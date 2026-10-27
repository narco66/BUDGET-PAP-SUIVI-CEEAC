<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ligne de préparation d'un lot : données d'origine, données normalisées et opération proposée. */
class ImportLigne extends Model
{
    public const OPERATIONS = [
        'inserer' => 'Insertion',
        'mettre_a_jour' => 'Mise à jour',
        'identique' => 'Identique (aucune action)',
        'ignorer' => 'Ignorée',
        'conflit' => 'Conflit',
        'erreur' => 'Erreur',
    ];

    /** Opérations qui écrivent en base à l'exécution. */
    public const ECRITURES = ['inserer', 'mettre_a_jour'];

    protected $fillable = [
        'import_lot_id', 'numero', 'donnees_brutes', 'donnees', 'cle_metier', 'operation',
        'cible_type', 'cible_id', 'differences', 'statut', 'message',
    ];

    protected $casts = [
        'donnees_brutes' => 'array',
        'donnees' => 'array',
        'differences' => 'array',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ImportLot::class, 'import_lot_id');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(ImportAnomalie::class);
    }
}
