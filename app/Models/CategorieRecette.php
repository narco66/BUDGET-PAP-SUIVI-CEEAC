<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategorieRecette extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'categories_recettes';

    public const TYPES = [
        'contribution_statutaire' => 'Contribution statutaire (État membre)',
        'contribution_ptf' => 'Contribution PTF / bailleur',
        'recette_communautaire' => 'Recette communautaire',
        'produit_financier' => 'Produit financier',
        'subvention' => 'Subvention',
        'autre' => 'Autre',
    ];

    protected $fillable = [
        'code', 'libelle', 'type', 'parent_id', 'ordre', 'actif', 'description',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];

    public function scopeActif($q)
    {
        return $q->where('actif', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre');
    }

    public function previsions(): HasMany
    {
        return $this->hasMany(PrevisionRecette::class, 'categorie_recette_id');
    }

    public function recettes(): HasMany
    {
        return $this->hasMany(Recette::class, 'categorie_recette_id');
    }
}
