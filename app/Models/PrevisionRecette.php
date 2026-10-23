<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrevisionRecette extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'previsions_recettes';

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'soumis' => 'Soumis',
        'valide' => 'Validé',
        'revise' => 'Révisé',
        'annule' => 'Annulé',
    ];

    /** Statuts engageants : une prévision validée/révisée ne se supprime plus (traçabilité). */
    public const STATUTS_VERROUILLES = ['valide', 'revise'];

    /** Interdit la suppression d'une prévision validée ou révisée (traçabilité budgétaire). */
    protected static function booted(): void
    {
        static::deleting(function (PrevisionRecette $prevision) {
            if ($prevision->estVerrouillee()) {
                throw new \RuntimeException(
                    "Suppression interdite : la prévision « {$prevision->reference} » est {$prevision->statut}."
                );
            }
        });
    }

    public function estVerrouillee(): bool
    {
        return in_array($this->statut, self::STATUTS_VERROUILLES, true);
    }

    protected $fillable = [
        'exercice_id', 'reference', 'categorie_recette_id',
        'source_financement_id', 'etat_membre_id', 'rubrique_id', 'ligne_budgetaire_id', 'contribution_id',
        'version', 'scenario', 'est_courante',
        'montant_prevu', 'montant_revise', 'statut', 'observations',
        'cree_par', 'valide_par', 'valide_le',
    ];

    protected $casts = [
        'version' => 'integer',
        'est_courante' => 'boolean',
        'montant_prevu' => 'decimal:2',
        'montant_revise' => 'decimal:2',
        'valide_le' => 'datetime',
    ];

    public function scopeCourante($q)
    {
        return $q->where('est_courante', true);
    }

    public function scopeValide($q)
    {
        return $q->whereIn('statut', ['valide', 'revise']);
    }

    /** Montant retenu pour le suivi : révisé si défini, sinon prévu. */
    protected function montantRetenu(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) ($this->montant_revise ?? $this->montant_prevu),
        );
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieRecette::class, 'categorie_recette_id');
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function etatMembre(): BelongsTo
    {
        return $this->belongsTo(EtatMembre::class);
    }

    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(BudgetRubrique::class, 'rubrique_id');
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function recette(): HasOne
    {
        return $this->hasOne(Recette::class);
    }
}
