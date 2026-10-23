<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Mission de suivi-évaluation (phase P2). */
class MissionSuivi extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'missions_suivi';

    protected $fillable = [
        'numero', 'exercice_id', 'structure_id', 'responsable_user_id', 'evaluation_periodique_id',
        'type', 'libelle', 'objectif', 'date_debut', 'date_fin', 'statut', 'score_mission', 'observations',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'score_mission' => 'decimal:2',
    ];

    public const TYPES = [
        'suivi' => 'Mission de suivi',
        'evaluation' => 'Mission d\'évaluation',
        'audit' => 'Mission d\'audit',
        'controle' => 'Mission de contrôle',
    ];

    public const STATUTS = [
        'planifiee' => 'Planifiée',
        'en_cours' => 'En cours',
        'cloturee' => 'Clôturée',
        'annulee' => 'Annulée',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function evaluationPeriodique(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriodique::class, 'evaluation_periodique_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(PlanAmelioration::class, 'mission_suivi_id');
    }
}
