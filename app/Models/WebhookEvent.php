<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Référentiel des événements webhook publiables (I-P2). */
class WebhookEvent extends Model
{
    protected $fillable = ['code', 'libelle', 'module', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];
}
