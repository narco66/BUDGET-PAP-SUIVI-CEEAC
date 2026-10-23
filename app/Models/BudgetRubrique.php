<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetRubrique extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'budget_rubriques';

    protected $fillable = ['paragraphe_id', 'code', 'intitule', 'ordre', 'statut'];

    public function scopeValide($q)
    {
        return $q->where('statut', 'valide');
    }

    public function paragraphe(): BelongsTo
    {
        return $this->belongsTo(BudgetParagraphe::class, 'paragraphe_id');
    }

    public function lignesBudgetaires(): HasMany
    {
        return $this->hasMany(LigneBudgetaire::class, 'rubrique_id');
    }
}
