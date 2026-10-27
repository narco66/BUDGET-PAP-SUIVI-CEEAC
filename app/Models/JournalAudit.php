<?php

namespace App\Models;

use App\Support\AuditEmpreinte;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Journal central (équivalent `audit_events`). Ajout seul : aucune modification ni suppression
 * par l'application (JNL-006) ; PostgreSQL le garantit en plus par déclencheur.
 */
class JournalAudit extends Model
{
    public $timestamps = false; // seul created_at, géré par la base

    protected $table = 'journaux_audit';

    public const CATEGORIES = ['metier' => 'Métier', 'securite' => 'Sécurité', 'technique' => 'Technique'];

    public const GRAVITES = ['information' => 'Information', 'faible' => 'Faible', 'modere' => 'Modéré', 'eleve' => 'Élevé', 'critique' => 'Critique'];

    public const RESULTATS = ['succes' => 'Succès', 'refus' => 'Refus', 'echec' => 'Échec'];

    protected $fillable = [
        'user_id', 'user_nom', 'role', 'auditable_type', 'auditable_id',
        'action', 'avant', 'apres', 'ip', 'url', 'user_agent', 'created_at',
        'uuid', 'code_evenement', 'categorie', 'gravite', 'module', 'resultat', 'motif', 'reference_objet',
        'structure_id', 'pour_compte_de', 'workflow_instance_id', 'workflow_tache_id', 'correlation_id',
        'request_id', 'source', 'occurred_at', 'effective_at', 'empreinte',
    ];

    // occurred_at / recorded_at / effective_at restent des chaînes : un cast « datetime » tronquerait
    // les microseconds et le fuseau à l'écriture. Lecture typée via instant().
    protected $casts = [
        'avant' => 'array',
        'apres' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'audit est en ajout seul : modification interdite.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'audit est en ajout seul : suppression interdite.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pour_compte_de');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Instant de survenance en UTC (microsecondes) ; à défaut created_at pour les lignes antérieures au module. */
    public function instant(): ?CarbonImmutable
    {
        $valeur = AuditEmpreinte::instant($this->getRawOriginal('occurred_at') ?? $this->getRawOriginal('created_at'));

        return $valeur ? CarbonImmutable::parse($valeur) : null;
    }

    public function instantEnregistrement(): ?CarbonImmutable
    {
        $valeur = AuditEmpreinte::instant($this->getRawOriginal('recorded_at'));

        return $valeur ? CarbonImmutable::parse($valeur) : null;
    }

    /** Empreinte recalculée à partir du contenu actuel de la ligne. */
    public function empreinteCalculee(): string
    {
        return AuditEmpreinte::calculer($this->getRawOriginal());
    }

    /** null = ligne antérieure au module (pas d'empreinte à l'écriture) ; true/false = intacte/altérée. */
    public function empreinteValide(): ?bool
    {
        return $this->empreinte === null ? null : hash_equals($this->empreinte, $this->empreinteCalculee());
    }

    public function libelleObjet(): ?string
    {
        if (! $this->auditable_type) {
            return null;
        }

        return class_basename($this->auditable_type).($this->reference_objet ? ' '.$this->reference_objet : ' #'.$this->auditable_id);
    }
}
