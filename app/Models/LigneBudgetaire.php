<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LigneBudgetaire extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'lignes_budgetaires';

    /** Valeur par défaut alignée sur la base : une ligne est active à la création. */
    protected $attributes = [
        'actif' => true,
        'statut_validation' => 'brouillon',
    ];

    protected $fillable = [
        'exercice_id', 'budget_id', 'structure_id', 'rubrique_id', 'source_financement_id', 'activite_id',
        'code', 'intitule', 'nature', 'date_entree_vigueur',
        'montant_ouvert', 'montant_demande', 'montant_propose', 'montant_revise', 'montant_engage', 'montant_liquide', 'montant_ordonnance', 'montant_paye',
        'actif',
        'statut_validation', 'soumis_le', 'valide_le', 'valide_par', 'motif_rejet',
    ];

    protected $casts = [
        'montant_ouvert' => 'decimal:2',
        'montant_demande' => 'decimal:2',
        'montant_propose' => 'decimal:2',
        'montant_revise' => 'decimal:2',
        'montant_engage' => 'decimal:2',
        'montant_liquide' => 'decimal:2',
        'montant_ordonnance' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'date_entree_vigueur' => 'date',
        'actif' => 'boolean',
        'soumis_le' => 'datetime',
        'valide_le' => 'datetime',
    ];

    /** Ligne mobilisable (active et non archivée). */
    /**
     * Idempotent upsert for natural keys protected by database unique indexes.
     * Soft-deleted rows still block unique keys, so restore them before saving.
     *
     * @param  array<string,mixed>  $attributes
     * @param  array<string,mixed>  $values
     */
    public static function updateOrCreateWithTrashed(array $attributes, array $values = []): self
    {
        $ligne = static::withTrashed()->firstOrNew($attributes);
        $ligne->fill($values);

        if ($ligne->exists && $ligne->trashed()) {
            $ligne->restore();
        }

        $ligne->save();

        return $ligne;
    }

    public function scopeActif($q)
    {
        return $q->where('actif', true);
    }

    /** Crédit alloué (révisé si défini, sinon ouvert). */
    protected function montantAlloue(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) ($this->montant_revise ?? $this->montant_ouvert),
        );
    }

    /** Crédit disponible à l'engagement = alloué - engagé. */
    protected function disponible(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->montant_alloue - (float) $this->montant_engage,
        );
    }

    /** Taux de consommation (% engagé / alloué). */
    protected function tauxConsommation(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->montant_alloue > 0
                ? round((float) $this->montant_engage / $this->montant_alloue * 100, 2)
                : 0.0,
        );
    }

    /** Ligne mobilisable (active et non archivée). */
    public function estDisponible(): bool
    {
        return $this->actif && ! $this->trashed();
    }

    /** Modifiable via le module Structure du Budget (brouillon ou rejetée). */
    public function estModifiable(): bool
    {
        return in_array($this->statut_validation ?? 'valide', ['brouillon', 'rejete'], true);
    }

    public function estSoumissible(): bool
    {
        return in_array($this->statut_validation ?? 'valide', ['brouillon', 'rejete'], true);
    }

    /** Un engagement de $montant est-il couvert par le disponible ? (ligne active obligatoire) */
    public function peutEngager(float $montant): bool
    {
        return $this->estDisponible() && $montant > 0 && $montant <= $this->disponible;
    }

    /** Suppression physique autorisée (aucun mouvement d'exécution). */
    public function peutEtreSupprimee(): bool
    {
        return (float) $this->montant_engage <= 0
            && (float) $this->montant_liquide <= 0
            && (float) $this->montant_ordonnance <= 0
            && (float) $this->montant_paye <= 0;
    }

    /** Message métier si la suppression est interdite. */
    public function raisonSuppressionInterdite(): ?string
    {
        if ((float) $this->montant_engage > 0) {
            return 'Suppression impossible : des crédits sont déjà engagés sur cette ligne.';
        }
        if ((float) $this->montant_liquide > 0) {
            return 'Suppression impossible : des liquidations existent sur cette ligne.';
        }
        if ((float) $this->montant_ordonnance > 0) {
            return 'Suppression impossible : des ordonnancements existent sur cette ligne.';
        }
        if ((float) $this->montant_paye > 0) {
            return 'Suppression impossible : des paiements existent sur cette ligne.';
        }

        return null;
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function budgetInstitutionnel(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(BudgetRubrique::class, 'rubrique_id');
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function sourcesFinancement(): HasMany
    {
        return $this->hasMany(LigneSourceFinancement::class);
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(EngagementImputation::class);
    }

    public function recettes(): HasMany
    {
        return $this->hasMany(Recette::class);
    }

    public function previsionsRecettes(): HasMany
    {
        return $this->hasMany(PrevisionRecette::class);
    }

    /** Source principale (FK ou pivot marquée principale). */
    public function sourcePrincipale(): ?SourceFinancement
    {
        $pivot = $this->sourcesFinancement()->where('est_principale', true)->with('sourceFinancement')->first();

        return $pivot?->sourceFinancement ?? $this->sourceFinancement;
    }
}
