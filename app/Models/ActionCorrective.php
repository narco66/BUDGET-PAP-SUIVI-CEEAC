<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Action corrective rattachée à un plan d'amélioration (phase P2). */
class ActionCorrective extends Model
{
    protected $table = 'actions_correctives';

    protected $fillable = [
        'plan_amelioration_id', 'recommandation_id', 'libelle', 'description',
        'echeance', 'statut', 'taux',
    ];

    protected $casts = [
        'echeance' => 'date',
        'taux' => 'decimal:2',
    ];

    public const STATUTS = [
        'en_attente' => 'En attente',
        'en_cours' => 'En cours',
        'realisee' => 'Réalisée',
        'non_realisee' => 'Non réalisée',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanAmelioration::class, 'plan_amelioration_id');
    }

    public function recommandation(): BelongsTo
    {
        return $this->belongsTo(Recommandation::class);
    }
}
