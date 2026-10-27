<?php

namespace App\Models;

use App\Models\Concerns\AjoutSeul;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Scellement d'un lot d'événements du journal central (tête de chaîne signée, dépôt hors base). */
class AuditScellement extends Model
{
    use AjoutSeul;

    public $timestamps = false;

    protected $table = 'audit_scellements';

    protected $guarded = ['id'];

    protected $casts = ['nombre' => 'integer'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scelle_par');
    }
}
