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

class SousProduit extends Model
{
    use HasFactory, SoftDeletes, HasIndicateurs, Auditable, HasRbmReferentiel;

    protected $table = 'sous_produits';

    protected $fillable = ['produit_id', 'code', 'intitule', 'description', 'ordre', 'statut', 'created_by', 'updated_by'];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class)->orderBy('ordre');
    }
}
