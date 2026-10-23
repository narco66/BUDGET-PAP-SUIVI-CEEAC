<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Planification de sauvegarde. */
class BackupSchedule extends Model
{
    protected $table = 'backup_schedules';

    protected $fillable = ['libelle', 'type', 'frequence', 'heure', 'retention_jours', 'actif', 'derniere_execution_le'];

    protected $casts = ['actif' => 'boolean', 'derniere_execution_le' => 'datetime', 'retention_jours' => 'integer'];

    public const FREQUENCES = [
        'quotidienne' => 'Quotidienne', 'hebdomadaire' => 'Hebdomadaire',
        'mensuelle' => 'Mensuelle', 'annuelle' => 'Annuelle',
    ];
}
