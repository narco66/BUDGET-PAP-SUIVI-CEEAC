<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RisqueStrategique extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'risques_strategiques';

    protected $fillable = [
        'plan_strategique_id', 'projet_id', 'code', 'description',
        'probabilite', 'impact', 'mitigation', 'responsable_user_id',
        'statut', 'created_by', 'updated_by',
    ];

    public function planStrategique(): BelongsTo
    {
        return $this->belongsTo(PlanStrategique::class);
    }

    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetStrategique::class, 'projet_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
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
