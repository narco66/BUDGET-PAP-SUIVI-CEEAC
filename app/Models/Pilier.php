<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Concerns\HasIndicateurs;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pilier extends Model
{
    use SoftDeletes, HasIndicateurs, Auditable, HasRbmReferentiel, HasFactory;

    protected $table = 'piliers';

    protected $fillable = ['exercice_id', 'pap_id', 'orientation_strategique_id', 'code', 'intitule', 'description', 'ordre', 'statut', 'created_by', 'updated_by'];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function pap(): BelongsTo
    {
        return $this->belongsTo(Pap::class);
    }

    public function orientationStrategique(): BelongsTo
    {
        return $this->belongsTo(OrientationStrategique::class);
    }

    public function axes(): HasMany
    {
        return $this->hasMany(Axe::class)->orderBy('ordre');
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }
}
