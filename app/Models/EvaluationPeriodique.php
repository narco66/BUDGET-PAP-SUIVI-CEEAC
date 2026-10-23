<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Snapshot de score S&E par exercice ou campagne (phase P0). */
class EvaluationPeriodique extends Model
{
    protected $table = 'evaluations_periodiques';

    protected $fillable = [
        'exercice_id', 'campagne_id', 'periode', 'type',
        'score_physique', 'score_iov', 'score_recommandations', 'score_formations',
        'score_conformite', 'score_global', 'niveau', 'details',
        'genere_par', 'genere_le',
    ];

    protected $casts = [
        'score_physique' => 'decimal:2',
        'score_iov' => 'decimal:2',
        'score_recommandations' => 'decimal:2',
        'score_formations' => 'decimal:2',
        'score_conformite' => 'decimal:2',
        'score_global' => 'decimal:2',
        'details' => 'array',
        'genere_le' => 'datetime',
    ];

    public const NIVEAUX = [
        'satisfaisant' => 'Satisfaisant',
        'partiel' => 'Partiellement satisfaisant',
        'insuffisant' => 'Insuffisant',
    ];

    public const TYPES = [
        'periodique' => 'Périodique',
        'campagne' => 'Campagne',
        'annuelle' => 'Annuelle',
        'impact' => 'Impact',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneSuivi::class, 'campagne_id');
    }

    public function generePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    public function recommandations(): HasMany
    {
        return $this->hasMany(Recommandation::class, 'evaluation_periodique_id');
    }

    public function missions(): HasMany
    {
        return $this->hasMany(MissionSuivi::class, 'evaluation_periodique_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(PlanAmelioration::class, 'evaluation_periodique_id');
    }
}
