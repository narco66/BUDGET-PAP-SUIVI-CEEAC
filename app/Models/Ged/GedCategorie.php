<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GedCategorie extends Model
{
    use SoftDeletes;

    protected $table = 'ged_categories';

    protected $fillable = [
        'code', 'libelle', 'description', 'parent_id', 'ordre', 'actif', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre');
    }

    public function typesDocuments(): HasMany
    {
        return $this->hasMany(GedTypeDocument::class, 'categorie_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
