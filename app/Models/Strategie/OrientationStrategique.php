<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrientationStrategique extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'orientations_strategiques';

    protected $fillable = [
        'plan_strategique_id', 'vision_id', 'code', 'intitule', 'ordre',
        'description', 'statut', 'created_by', 'updated_by',
    ];

    protected $casts = ['ordre' => 'integer'];

    public function planStrategique(): BelongsTo
    {
        return $this->belongsTo(PlanStrategique::class);
    }

    public function vision(): BelongsTo
    {
        return $this->belongsTo(Vision::class);
    }

    public function programmesStrategiques(): HasMany
    {
        return $this->hasMany(ProgrammeStrategique::class);
    }

    public function objectifs(): HasMany
    {
        return $this->hasMany(ObjectifStrategique::class);
    }

    public function alignements(): HasMany
    {
        return $this->hasMany(AlignementStrategique::class);
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
