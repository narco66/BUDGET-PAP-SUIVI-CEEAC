<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tâche « Mes Tâches » : action humaine attendue sur un dossier. Nature « validation » : étape de circuit
 * à traiter ; nature « preparation » : action hors circuit (soumettre, corriger, préparer, solder).
 */
class WorkflowTache extends Model
{
    public const OUVERTS = ['a_traiter', 'en_cours', 'suspendue'];

    public const STATUTS = [
        'a_traiter' => 'À traiter',
        'en_cours' => 'En cours',
        'suspendue' => 'Suspendue',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
    ];

    public const PRIORITES = ['faible' => 'Faible', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'];

    public const DECISIONS = ['valide' => 'Validée', 'rejete' => 'Rejetée', 'renvoye' => 'Retournée pour correction', 'traitee' => 'Action réalisée'];

    public const NATURE_VALIDATION = 'validation';

    public const NATURE_PREPARATION = 'preparation';

    protected $table = 'workflow_taches';

    protected $fillable = [
        'reference', 'nature', 'action_code', 'affectation_defaut', 'workflow_instance_id', 'workflow_instance_etape_id', 'workflow_code', 'module',
        'objet_type', 'objet_id', 'objet_libelle', 'structure_id', 'etape_ordre', 'role', 'libelle_etape',
        'statut', 'priorite', 'priorite_manuelle', 'decision', 'assigne_a', 'pris_en_charge_le',
        'suspendue_le', 'motif_suspension', 'echeance_le', 'termine_le', 'termine_par', 'version',
    ];

    protected $casts = [
        'priorite_manuelle' => 'boolean',
        'affectation_defaut' => 'boolean',
        'pris_en_charge_le' => 'datetime',
        'suspendue_le' => 'datetime',
        'echeance_le' => 'datetime',
        'termine_le' => 'datetime',
        'version' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function etape(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstanceEtape::class, 'workflow_instance_etape_id');
    }

    public function objet(): MorphTo
    {
        return $this->morphTo();
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function assigne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }

    public function terminePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'termine_par');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(WorkflowTacheEvenement::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeOuvertes(Builder $q): Builder
    {
        return $q->whereIn('statut', self::OUVERTS);
    }

    public function estOuverte(): bool
    {
        return in_array($this->statut, self::OUVERTS, true);
    }

    /** Tâches du même parcours : la même instance de circuit, ou, hors circuit, les actions du même dossier. */
    public function tachesLiees(): Builder
    {
        return $this->workflow_instance_id
            ? self::query()->where('workflow_instance_id', $this->workflow_instance_id)
            : self::query()->whereNull('workflow_instance_id')->where('objet_type', $this->objet_type)->where('objet_id', $this->objet_id);
    }

    public function estPreparation(): bool
    {
        return $this->nature === self::NATURE_PREPARATION;
    }

    public function enRetard(): bool
    {
        return $this->estOuverte() && $this->echeance_le !== null && $this->echeance_le->isPast();
    }
}
