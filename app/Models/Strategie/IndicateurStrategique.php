<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\Indicateur;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndicateurStrategique extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'indicateurs_strategiques';

    protected $fillable = [
        'objectif_strategique_id', 'code', 'libelle', 'unite', 'periodicite',
        'valeur_reference', 'valeur_cible', 'seuil_alerte', 'indicateur_id',
        'statut', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'valeur_reference' => 'decimal:4',
        'valeur_cible' => 'decimal:4',
        'seuil_alerte' => 'decimal:4',
    ];

    public function objectifStrategique(): BelongsTo
    {
        return $this->belongsTo(ObjectifStrategique::class);
    }

    public function indicateurRbm(): BelongsTo
    {
        return $this->belongsTo(Indicateur::class, 'indicateur_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
