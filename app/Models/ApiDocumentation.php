<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiDocumentation extends Model
{
    protected $fillable = ['version', 'titre', 'chemin_fichier', 'format', 'actif', 'publie_le'];

    protected $casts = ['actif' => 'boolean', 'publie_le' => 'datetime'];
}
