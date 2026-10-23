<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Incident technique (ITIL). Cycle de vie : Déclaration → Diagnostic → Correction → Validation → Clôture.
 */
class Incident extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'incident_categorie_id', 'structure_id', 'declare_par', 'assigne_a',
        'titre', 'description', 'gravite', 'source', 'statut', 'impact', 'cause_racine',
        'resolution', 'date_detection', 'date_resolution',
    ];

    protected $casts = ['date_detection' => 'datetime', 'date_resolution' => 'datetime'];

    public const GRAVITES = [
        'faible' => 'Faible', 'moyenne' => 'Moyenne', 'elevee' => 'Élevée', 'critique' => 'Critique',
    ];

    public const SOURCES = ['manuelle' => 'Déclaration manuelle', 'monitoring' => 'Monitoring', 'alerte' => 'Alerte'];

    public const STATUTS = [
        'ouvert' => 'Ouvert', 'en_diagnostic' => 'En diagnostic', 'en_correction' => 'En correction',
        'en_validation' => 'En validation', 'resolu' => 'Résolu', 'clos' => 'Clôturé', 'rejete' => 'Rejeté',
    ];

    /** Transition « normale » suivante du cycle ITIL. */
    public const TRANSITIONS = [
        'ouvert' => 'en_diagnostic',
        'en_diagnostic' => 'en_correction',
        'en_correction' => 'en_validation',
        'en_validation' => 'resolu',
        'resolu' => 'clos',
    ];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(IncidentCategorie::class, 'incident_categorie_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function declarant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declare_par');
    }

    public function assigne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(IncidentAction::class)->orderByDesc('id');
    }

    public function estClos(): bool
    {
        return in_array($this->statut, ['clos', 'rejete'], true);
    }

    public function prochainStatut(): ?string
    {
        return self::TRANSITIONS[$this->statut] ?? null;
    }

    public static function graviteClasse(string $gravite): string
    {
        return ['faible' => 'success', 'moyenne' => 'info', 'elevee' => 'warning', 'critique' => 'danger'][$gravite] ?? 'secondary';
    }

    public static function statutClasse(string $statut): string
    {
        return [
            'ouvert' => 'danger', 'en_diagnostic' => 'warning', 'en_correction' => 'info',
            'en_validation' => 'primary', 'resolu' => 'success', 'clos' => 'secondary', 'rejete' => 'dark',
        ][$statut] ?? 'secondary';
    }

    public function scopeOuvert($q)
    {
        return $q->whereNotIn('statut', ['clos', 'rejete']);
    }
}
