<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Snapshot du contrôle d'intégrité des données (P2-10). */
class AuditIntegriteData extends Model
{
    protected $table = 'audit_integrite_data';

    protected $fillable = [
        'exercice_id', 'score_conformite', 'anomalies_total', 'anomalies_critiques',
        'modules', 'snapshot', 'audite_le',
    ];

    protected $casts = [
        'modules' => 'array',
        'snapshot' => 'array',
        'audite_le' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
