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

class Produit extends Model
{
    use HasFactory, SoftDeletes, HasIndicateurs, Auditable, HasRbmReferentiel;

    protected $fillable = ['axe_id', 'structure_id', 'code', 'intitule', 'description', 'ordre', 'statut', 'created_by', 'updated_by'];

    public function axe(): BelongsTo
    {
        return $this->belongsTo(Axe::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function sousProduits(): HasMany
    {
        return $this->hasMany(SousProduit::class)->orderBy('ordre');
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class)->orderBy('ordre');
    }
}
