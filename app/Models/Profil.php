<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

class Profil extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = ['code', 'libelle', 'type', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'profil_role');
    }

    public function utilisateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_profils')->withTimestamps();
    }
}
