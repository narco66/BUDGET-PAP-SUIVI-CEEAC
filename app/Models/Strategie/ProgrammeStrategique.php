<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\Pilier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgrammeStrategique extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'programmes_strategiques';

    protected $fillable = [
        'orientation_strategique_id', 'code', 'intitule', 'responsable_user_id',
        'date_debut', 'date_fin', 'budget_previsionnel', 'objectifs', 'resultats_attendus',
        'statut', 'version', 'description', 'pilier_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'budget_previsionnel' => 'decimal:2',
        'version' => 'integer',
    ];

    public function orientationStrategique(): BelongsTo
    {
        return $this->belongsTo(OrientationStrategique::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function pilier(): BelongsTo
    {
        return $this->belongsTo(Pilier::class);
    }

    public function projets(): HasMany
    {
        return $this->hasMany(ProjetStrategique::class, 'programme_strategique_id');
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
