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

class Axe extends Model
{
    use SoftDeletes, HasIndicateurs, Auditable, HasRbmReferentiel, HasFactory;

    protected $fillable = ['pilier_id', 'code', 'intitule', 'objectif_strategique', 'ordre', 'statut', 'created_by', 'updated_by'];

    public function pilier(): BelongsTo
    {
        return $this->belongsTo(Pilier::class);
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class)->orderBy('ordre');
    }
}
