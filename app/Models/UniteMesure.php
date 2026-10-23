<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UniteMesure extends Model
{
    use Auditable, HasRbmReferentiel, SoftDeletes;

    protected $table = 'unites_mesure';

    protected $fillable = ['code', 'libelle', 'symbole', 'description', 'ordre', 'statut', 'created_by', 'updated_by'];

    public function indicateurs(): HasMany
    {
        return $this->hasMany(Indicateur::class, 'unite_mesure_id');
    }

    public function scopeRecherche($query, ?string $q)
    {
        return $query->when($q, fn ($b) => $b->where(fn ($s) => $s
            ->where('code', 'like', "%{$q}%")
            ->orWhere('libelle', 'like', "%{$q}%")
            ->orWhere('symbole', 'like', "%{$q}%")));
    }
}
