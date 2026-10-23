<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditDepense extends Model
{
    protected $table = 'audit_depenses';

    protected $fillable = [
        'exercice_id', 'engagement_id', 'reference', 'score_conformite',
        'snapshot', 'anomalies', 'audite_par', 'audite_le',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'anomalies' => 'array',
        'audite_le' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function auditeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audite_par');
    }
}
