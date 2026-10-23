<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SourceVerification extends Model
{
    use Auditable, HasRbmReferentiel, SoftDeletes, HasGedDocuments;

    protected $table = 'sources_verification';

    protected $fillable = ['code', 'libelle', 'description', 'url', 'ordre', 'statut', 'created_by', 'updated_by'];

    public function indicateurs(): BelongsToMany
    {
        return $this->belongsToMany(Indicateur::class, 'indicateur_source_verification')
            ->withTimestamps();
    }

    public function scopeRecherche($query, ?string $q)
    {
        return $query->when($q, fn ($b) => $b->where(fn ($s) => $s
            ->where('code', 'like', "%{$q}%")
            ->orWhere('libelle', 'like', "%{$q}%")));
    }
}
