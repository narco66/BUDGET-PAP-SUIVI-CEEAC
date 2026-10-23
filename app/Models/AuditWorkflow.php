<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditWorkflow extends Model
{
    public $timestamps = false;

    protected $table = 'audit_workflow';

    protected $fillable = [
        'workflow_instance_id', 'workflow_code', 'objet_type', 'objet_id',
        'action', 'etape_ordre', 'acteur_id', 'observation', 'categorie_rejet',
        'meta', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acteur_id');
    }
}
