<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObjectifStrategique extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'objectifs_strategiques';

    protected $fillable = [
        'plan_strategique_id', 'orientation_strategique_id', 'code', 'intitule',
        'description', 'ordre', 'statut', 'created_by', 'updated_by',
    ];

    protected $casts = ['ordre' => 'integer'];

    public function planStrategique(): BelongsTo
    {
        return $this->belongsTo(PlanStrategique::class);
    }

    public function orientationStrategique(): BelongsTo
    {
        return $this->belongsTo(OrientationStrategique::class);
    }

    public function indicateurs(): HasMany
    {
        return $this->hasMany(IndicateurStrategique::class);
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
