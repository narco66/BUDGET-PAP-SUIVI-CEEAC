<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = ['code', 'libelle', 'type_objet', 'description', 'actif', 'version', 'published_at', 'created_by'];

    protected $casts = ['actif' => 'boolean', 'published_at' => 'datetime'];

    public function etapes(): HasMany
    {
        return $this->hasMany(WorkflowEtape::class)->orderBy('ordre');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class)->orderByDesc('numero');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
