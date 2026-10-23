<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetParagraphe extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'budget_paragraphes';

    protected $fillable = ['article_id', 'code', 'intitule', 'ordre', 'statut'];

    public function scopeValide($q)
    {
        return $q->where('statut', 'valide');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(BudgetArticle::class, 'article_id');
    }

    public function rubriques(): HasMany
    {
        return $this->hasMany(BudgetRubrique::class, 'paragraphe_id')->orderBy('ordre');
    }
}
