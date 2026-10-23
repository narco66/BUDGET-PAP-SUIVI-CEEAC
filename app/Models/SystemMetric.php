<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Instantané de santé technique de la plateforme (supervision). */
class SystemMetric extends Model
{
    public $timestamps = false;

    protected $table = 'system_metrics';

    protected $fillable = [
        'capture_le', 'statut_global', 'disque_libre_pct', 'disque_libre_octets',
        'jobs_en_attente', 'jobs_echoues', 'sessions_actives', 'echecs_connexion_24h',
        'charge_systeme', 'details', 'created_at',
    ];

    protected $casts = [
        'capture_le' => 'datetime',
        'created_at' => 'datetime',
        'details' => 'array',
        'disque_libre_pct' => 'decimal:2',
        'charge_systeme' => 'decimal:2',
    ];
}
