<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceEtape extends Model
{
    protected $table = 'workflow_instance_etapes';

    protected $fillable = [
        'instance_id', 'workflow_etape_id', 'ordre', 'role', 'libelle',
        'statut', 'acteur_id', 'decision_le', 'observation', 'categorie_rejet',
        'echeance_le', 'derniere_relance_le', 'ip', 'user_agent', 'hash_visa',
    ];

    protected $casts = [
        'decision_le' => 'datetime',
        'echeance_le' => 'datetime',
        'derniere_relance_le' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acteur_id');
    }

    /** Définition de l'étape dans le circuit (libellé, délai). */
    public function etapeDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowEtape::class, 'workflow_etape_id');
    }

    /** Tâches « Mes Tâches » nées de cette étape (une par passage à l'état « à traiter »). */
    public function taches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkflowTache::class, 'workflow_instance_etape_id');
    }
}
