<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceEtape extends Model
{
    protected $table = 'workflow_instance_etapes';

    protected $fillable = [
        'instance_id', 'workflow_etape_id', 'ordre', 'role',
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
}
