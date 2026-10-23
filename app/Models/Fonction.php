<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fonction extends Model
{
    use Auditable;

    protected $fillable = ['code', 'libelle', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
