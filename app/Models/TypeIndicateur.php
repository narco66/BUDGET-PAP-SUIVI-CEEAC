<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeIndicateur extends Model
{
    use Auditable, HasRbmReferentiel, SoftDeletes, HasFactory;

    protected $table = 'types_indicateurs';

    protected $fillable = ['code', 'libelle', 'description', 'ordre', 'statut', 'created_by', 'updated_by'];

    public function scopeRecherche($query, ?string $q)
    {
        return $query->when($q, fn ($b) => $b->where(fn ($s) => $s
            ->where('code', 'like', "%{$q}%")
            ->orWhere('libelle', 'like', "%{$q}%")));
    }
}
