<?php

namespace App\Models\Strategie;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AlignementStrategique extends Model
{
    protected $table = 'alignements_strategiques';

    protected $fillable = [
        'vision_id', 'orientation_strategique_id', 'plan_strategique_id',
        'alignable_type', 'alignable_id', 'niveau_alignement', 'commentaire',
        'created_by',
    ];

    public function vision(): BelongsTo
    {
        return $this->belongsTo(Vision::class);
    }

    public function orientationStrategique(): BelongsTo
    {
        return $this->belongsTo(OrientationStrategique::class);
    }

    public function planStrategique(): BelongsTo
    {
        return $this->belongsTo(PlanStrategique::class);
    }

    public function alignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
