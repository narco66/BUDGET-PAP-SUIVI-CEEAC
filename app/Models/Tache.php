<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasIndicateurs;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tache extends Model
{
    use HasFactory, SoftDeletes, HasIndicateurs, Auditable, HasRbmReferentiel;

    protected $table = 'taches';

    protected $fillable = [
        'activite_id', 'responsable_user_id', 'code', 'intitule',
        'date_debut', 'date_fin', 'montant', 'taux_realisation', 'statut', 'ordre',
        'statut_referentiel', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'montant' => 'decimal:2',
    ];

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }
}
