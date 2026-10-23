<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetArticle extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'budget_articles';

    protected $fillable = ['titre_id', 'code', 'intitule', 'ordre', 'statut'];

    public function scopeValide($q)
    {
        return $q->where('statut', 'valide');
    }

    public function titre(): BelongsTo
    {
        return $this->belongsTo(BudgetTitre::class, 'titre_id');
    }

    public function paragraphes(): HasMany
    {
        return $this->hasMany(BudgetParagraphe::class, 'article_id')->orderBy('ordre');
    }
}
