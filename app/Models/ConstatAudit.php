<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Constat d'audit + recommandation associée et son suivi (responsable, échéance, statut, taux d'avancement).
 */
class ConstatAudit extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'constats_audit';

    protected $fillable = [
        'numero', 'mission_audit_id', 'responsable_user_id', 'libelle', 'description', 'cause',
        'gravite', 'recommandation', 'echeance', 'statut', 'taux_avancement', 'created_by',
    ];

    protected $casts = ['echeance' => 'date', 'taux_avancement' => 'integer'];

    public const GRAVITES = [
        'faible' => 'Faible', 'moyenne' => 'Moyenne', 'elevee' => 'Élevée', 'critique' => 'Critique',
    ];

    public const STATUTS = [
        'ouvert' => 'Ouvert', 'en_cours' => 'En cours', 'realise' => 'Réalisé', 'non_realise' => 'Non réalisé',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(MissionAudit::class, 'mission_audit_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function estSolde(): bool
    {
        return in_array($this->statut, ['realise', 'non_realise'], true);
    }

    public static function graviteClasse(string $gravite): string
    {
        return ['faible' => 'success', 'moyenne' => 'info', 'elevee' => 'warning', 'critique' => 'danger'][$gravite] ?? 'secondary';
    }
}
