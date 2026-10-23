<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgrammationPluriannuelle extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'programmations_pluriannuelles';

    protected $fillable = [
        'plan_strategique_id', 'reference', 'annee_debut', 'annee_fin',
        'statut', 'version', 'description', 'scenarios',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'annee_debut' => 'integer',
        'annee_fin' => 'integer',
        'version' => 'integer',
        'scenarios' => 'array',
    ];

    public function planStrategique(): BelongsTo
    {
        return $this->belongsTo(PlanStrategique::class);
    }

    public function programmationsAnnuelles(): HasMany
    {
        return $this->hasMany(ProgrammationAnnuelle::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
