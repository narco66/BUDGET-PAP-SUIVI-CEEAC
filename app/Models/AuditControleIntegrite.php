<?php

namespace App\Models;

use App\Models\Concerns\AjoutSeul;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rapport horodaté d'un contrôle d'intégrité du journal (conforme, anomalie, non vérifiable). */
class AuditControleIntegrite extends Model
{
    use AjoutSeul;

    public $timestamps = false;

    protected $table = 'audit_controles_integrite';

    protected $guarded = ['id'];

    protected $casts = ['anomalies' => 'array', 'horloge' => 'array'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execute_par');
    }
}
