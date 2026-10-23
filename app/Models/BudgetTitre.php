<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetTitre extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'budget_titres';

    protected $fillable = ['sens', 'code', 'intitule', 'ordre', 'statut'];

    /** Sens budgétaires de la nomenclature CEEAC. */
    public const SENS = [
        'recette' => 'Recette',
        'depense' => 'Dépense',
        'financement' => 'Financement',
        'hors_bilan' => 'Hors-bilan',
    ];

    /** Statuts du workflow de validation de la nomenclature (L5). */
    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'valide' => 'Validé',
    ];

    public function scopeValide($q)
    {
        return $q->where('statut', 'valide');
    }

    public function estValide(): bool
    {
        return ($this->statut ?? 'valide') === 'valide';
    }

    public function articles(): HasMany
    {
        return $this->hasMany(BudgetArticle::class, 'titre_id')->orderBy('ordre');
    }

    public function scopeDepense($q)
    {
        return $q->where('sens', 'depense');
    }

    public function scopeRecette($q)
    {
        return $q->where('sens', 'recette');
    }
}
