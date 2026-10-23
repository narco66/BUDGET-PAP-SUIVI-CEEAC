<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dispositif de contrôle interne cartographié selon le référentiel COSO (5 composantes)
 * avec évaluation d'efficacité.
 */
class ControleInterne extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'controles_internes';

    protected $fillable = [
        'code', 'composante', 'libelle', 'description', 'processus', 'structure_id',
        'responsable_user_id', 'type', 'frequence', 'evaluation', 'date_evaluation', 'actif', 'created_by',
    ];

    protected $casts = ['date_evaluation' => 'date', 'actif' => 'boolean'];

    /** Les 5 composantes du référentiel COSO (dans l'ordre). */
    public const COMPOSANTES = [
        'environnement' => 'Environnement de contrôle',
        'evaluation_risques' => 'Évaluation des risques',
        'activites_controle' => 'Activités de contrôle',
        'information_communication' => 'Information & communication',
        'pilotage' => 'Pilotage / surveillance',
    ];

    public const TYPES = ['preventif' => 'Préventif', 'detectif' => 'Détectif', 'correctif' => 'Correctif'];

    public const FREQUENCES = [
        'permanent' => 'Permanent', 'quotidien' => 'Quotidien', 'hebdomadaire' => 'Hebdomadaire',
        'mensuel' => 'Mensuel', 'trimestriel' => 'Trimestriel', 'annuel' => 'Annuel', 'ponctuel' => 'Ponctuel',
    ];

    public const EVALUATIONS = [
        'efficace' => 'Efficace', 'partiel' => 'Partiellement efficace',
        'inefficace' => 'Inefficace', 'non_evalue' => 'Non évalué',
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public static function evaluationClasse(string $evaluation): string
    {
        return ['efficace' => 'success', 'partiel' => 'warning', 'inefficace' => 'danger', 'non_evalue' => 'secondary'][$evaluation] ?? 'secondary';
    }
}
