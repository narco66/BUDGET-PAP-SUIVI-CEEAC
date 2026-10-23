<?php

namespace App\Models\Ged;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GedWorkflow extends Model
{
    use SoftDeletes;

    protected $table = 'ged_workflows';

    protected $fillable = [
        'code', 'libelle', 'description', 'type_document_id', 'actif', 'created_by',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(GedTypeDocument::class, 'type_document_id');
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(GedWorkflowEtape::class, 'workflow_id')->orderBy('ordre');
    }
}
