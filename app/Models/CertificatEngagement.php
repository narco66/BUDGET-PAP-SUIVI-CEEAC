<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificatEngagement extends Model
{
    protected $table = 'certificats_engagement';

    protected $fillable = [
        'engagement_id', 'numero', 'type', 'emis_par', 'emis_le', 'metadata',
    ];

    protected $casts = [
        'emis_le' => 'datetime',
        'metadata' => 'array',
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function emetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emis_par');
    }
}
