<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Action journalisée sur un incident (diagnostic, correction, validation, clôture…). */
class IncidentAction extends Model
{
    public $timestamps = false;

    protected $table = 'incident_actions';

    protected $fillable = [
        'incident_id', 'auteur_user_id', 'type', 'libelle', 'description',
        'statut_avant', 'statut_apres', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public const TYPES = [
        'diagnostic' => 'Diagnostic', 'correction' => 'Correction', 'validation' => 'Validation',
        'communication' => 'Communication', 'cloture' => 'Clôture', 'rejet' => 'Rejet',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_user_id');
    }
}
