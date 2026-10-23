<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Activite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Programme extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = ['code', 'intitule', 'structure_id', 'pilier_id', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function pilier(): BelongsTo
    {
        return $this->belongsTo(Pilier::class);
    }

    /** Requête des activités PAP du pilier rattachées à la structure de ce programme. */
    public function activitesQuery(): Builder
    {
        return Activite::query()
            ->where('structure_id', $this->structure_id)
            ->whereHas('sousProduit.produit.axe', fn ($q) => $q->where('pilier_id', $this->pilier_id));
    }
}
