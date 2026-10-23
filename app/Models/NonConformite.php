<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Non-conformité (écart) et son plan de remédiation intégré
 * (action, responsable, échéance, statut, taux d'avancement).
 */
class NonConformite extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'non_conformites';

    protected $fillable = [
        'code', 'controle_interne_id', 'mission_audit_id', 'structure_id', 'responsable_user_id',
        'source', 'libelle', 'description', 'gravite', 'processus', 'date_detection',
        'plan_action', 'echeance', 'statut', 'taux_avancement', 'created_by',
    ];

    protected $casts = ['date_detection' => 'date', 'echeance' => 'date', 'taux_avancement' => 'integer'];

    public const SOURCES = [
        'controle_interne' => 'Contrôle interne', 'audit' => 'Mission d\'audit',
        'reglementaire' => 'Conformité réglementaire', 'autre' => 'Autre',
    ];

    public const GRAVITES = [
        'faible' => 'Faible', 'moyenne' => 'Moyenne', 'elevee' => 'Élevée', 'critique' => 'Critique',
    ];

    public const STATUTS = [
        'ouverte' => 'Ouverte', 'en_traitement' => 'En traitement', 'resolue' => 'Résolue', 'cloturee' => 'Clôturée',
    ];

    public function controleInterne(): BelongsTo
    {
        return $this->belongsTo(ControleInterne::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function estOuverte(): bool
    {
        return in_array($this->statut, ['ouverte', 'en_traitement'], true);
    }

    public static function graviteClasse(string $gravite): string
    {
        return ['faible' => 'success', 'moyenne' => 'info', 'elevee' => 'warning', 'critique' => 'danger'][$gravite] ?? 'secondary';
    }

    public function scopeOuverte($q)
    {
        return $q->whereIn('statut', ['ouverte', 'en_traitement']);
    }
}
