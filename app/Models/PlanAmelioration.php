<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Plan d'amélioration issu d'une évaluation S&E (phase P2). */
class PlanAmelioration extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'plans_amelioration';

    protected $fillable = [
        'numero', 'exercice_id', 'evaluation_periodique_id', 'mission_suivi_id',
        'structure_id', 'responsable_user_id', 'intitule', 'constat',
        'date_debut', 'echeance', 'statut', 'taux_realisation',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'echeance' => 'date',
        'taux_realisation' => 'decimal:2',
    ];

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'valide' => 'Validé',
        'en_cours' => 'En cours',
        'cloture' => 'Clôturé',
        'abandonne' => 'Abandonné',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function evaluationPeriodique(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriodique::class, 'evaluation_periodique_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(MissionSuivi::class, 'mission_suivi_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ActionCorrective::class, 'plan_amelioration_id');
    }

    public function recalculerTaux(): void
    {
        $actions = $this->actions;
        if ($actions->isEmpty()) {
            return;
        }

        $this->update(['taux_realisation' => round((float) $actions->avg('taux'), 2)]);
    }
}
