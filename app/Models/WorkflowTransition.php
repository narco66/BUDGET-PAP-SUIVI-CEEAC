<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    protected $fillable = [
        'workflow_id', 'workflow_version_id', 'etape_source_ordre', 'etape_cible_ordre',
        'condition_id', 'type', 'libelle', 'priorite',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(WorkflowCondition::class, 'condition_id');
    }
}
