<?php

namespace App\Models\Ged;

use App\Models\Exercice;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GedDossier extends Model
{
    use SoftDeletes;

    protected $table = 'ged_dossiers';

    protected $fillable = [
        'reference', 'titre', 'description', 'parent_id', 'categorie_id', 'structure_id',
        'exercice_id', 'module_source', 'chemin_codification', 'est_archive', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['est_archive' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function sousDossiers(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(GedCategorie::class, 'categorie_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GedDocument::class, 'dossier_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
