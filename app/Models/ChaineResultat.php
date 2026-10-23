<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChaineResultat extends Model
{
    use Auditable, HasRbmReferentiel, SoftDeletes;

    protected $table = 'chaines_resultats';

    protected $fillable = [
        'exercice_id', 'code', 'intitule', 'description', 'elements', 'ordre', 'statut', 'created_by', 'updated_by',
    ];

    protected $casts = ['elements' => 'array'];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
