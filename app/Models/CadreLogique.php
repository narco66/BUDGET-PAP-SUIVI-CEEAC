<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CadreLogique extends Model
{
    use Auditable, HasRbmReferentiel, SoftDeletes;

    protected $table = 'cadres_logiques';

    protected $fillable = [
        'exercice_id', 'code', 'intitule', 'description', 'matrice', 'elements', 'ordre', 'statut', 'created_by', 'updated_by',
    ];

    protected $casts = ['matrice' => 'array', 'elements' => 'array'];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
