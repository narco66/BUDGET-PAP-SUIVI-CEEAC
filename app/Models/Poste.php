<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poste extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = ['code', 'libelle', 'fonction_id', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function fonction(): BelongsTo
    {
        return $this->belongsTo(Fonction::class);
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
