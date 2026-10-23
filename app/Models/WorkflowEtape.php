<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowEtape extends Model
{
    protected $table = 'workflow_etapes';

    protected $fillable = [
        'workflow_id', 'ordre', 'libelle', 'role', 'delai_jours',
        'type_approbation', 'condition_json', 'groupe_parallele',
    ];

    protected $casts = ['condition_json' => 'array'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
