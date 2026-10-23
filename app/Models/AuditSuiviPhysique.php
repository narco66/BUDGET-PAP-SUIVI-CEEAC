<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Snapshot d'audit suivi physique (phase S4). */
class AuditSuiviPhysique extends Model
{
    protected $table = 'audit_suivi_physique';

    protected $fillable = ['exercice_id', 'auditeur_id', 'score_conformite', 'rapport'];

    protected $casts = [
        'rapport' => 'array',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function auditeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditeur_id');
    }
}
