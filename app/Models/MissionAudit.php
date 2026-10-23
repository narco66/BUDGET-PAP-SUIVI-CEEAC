<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mission d'audit (plan annuel + exécution, cycle INTOSAI). Mission → Constats → Recommandations → Suivi.
 */
class MissionAudit extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'missions_audit';

    protected $fillable = [
        'numero', 'exercice_id', 'structure_id', 'chef_mission_user_id', 'type', 'libelle',
        'domaine', 'objectif', 'perimetre', 'date_debut', 'date_fin', 'statut', 'conclusion',
        'score_conformite', 'created_by',
    ];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date'];

    public const TYPES = [
        'interne' => 'Audit interne', 'externe' => 'Audit externe', 'conformite' => 'Audit de conformité',
        'performance' => 'Audit de performance', 'financier' => 'Audit financier',
    ];

    public const STATUTS = [
        'planifiee' => 'Planifiée', 'en_cours' => 'En cours', 'cloturee' => 'Clôturée', 'annulee' => 'Annulée',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function chefMission(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_mission_user_id');
    }

    public function constats(): HasMany
    {
        return $this->hasMany(ConstatAudit::class)->orderByDesc('id');
    }

    public function estCloturee(): bool
    {
        return in_array($this->statut, ['cloturee', 'annulee'], true);
    }

    public function estModifiable(): bool
    {
        return ! $this->estCloturee();
    }

    /** Constats non encore soldés (recommandations en attente de réalisation). */
    public function constatsOuverts(): int
    {
        return $this->constats()->whereIn('statut', ['ouvert', 'en_cours'])->count();
    }

    public function scopeOuverte($q)
    {
        return $q->whereIn('statut', ['planifiee', 'en_cours']);
    }
}
