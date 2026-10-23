<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPieces;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vision extends Model
{
    use SoftDeletes, Auditable, HasPieces;

    protected $table = 'visions';

    protected $fillable = [
        'reference', 'intitule', 'periode', 'date_debut', 'date_fin',
        'statut', 'version', 'description', 'document_officiel',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'version' => 'integer',
    ];

    public function plansStrategiques(): HasMany
    {
        return $this->hasMany(PlanStrategique::class);
    }

    public function orientations(): HasMany
    {
        return $this->hasMany(OrientationStrategique::class);
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
