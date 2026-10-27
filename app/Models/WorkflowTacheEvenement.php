<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Événement immuable du journal d'une tâche (création, prise en charge, décision, commentaire…). */
class WorkflowTacheEvenement extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'creation' => 'Tâche créée',
        'prise_en_charge' => 'Prise en charge',
        'liberation' => 'Libérée',
        'reaffectation' => 'Réaffectée',
        'suspension' => 'Suspendue',
        'reprise' => 'Reprise',
        'commentaire' => 'Observation',
        'decision' => 'Décision',
        'annulation' => 'Annulée',
        'priorite' => 'Priorité modifiée',
        'rappel' => 'Rappel envoyé',
        'escalade' => 'Escalade hiérarchique',
    ];

    protected $table = 'workflow_tache_evenements';

    protected $fillable = ['workflow_tache_id', 'type', 'user_id', 'pour_compte_de', 'commentaire', 'donnees'];

    protected $casts = ['donnees' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        // Journal non modifiable par les opérations ordinaires (§ 17.3).
        static::updating(fn () => throw new LogicException('Un événement de tâche est immuable.'));
        static::deleting(fn () => throw new LogicException('Un événement de tâche ne peut pas être supprimé.'));
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(WorkflowTache::class, 'workflow_tache_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pourCompteDe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pour_compte_de');
    }
}
