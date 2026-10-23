<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recette extends Model
{
    use Auditable, SoftDeletes;

    public const STATUTS = [
        'prevue' => 'Prévue',
        'partiellement_realisee' => 'Partiellement réalisée',
        'realisee' => 'Réalisée',
        'annulee' => 'Annulée',
    ];

    /** Statuts comptabilisés : verrouillés contre toute suppression (contrôle interne). */
    public const STATUTS_VERROUILLES = ['partiellement_realisee', 'realisee'];

    /**
     * Interdit toute suppression (logique ou physique) d'une recette comptabilisée.
     * Contrôle interne : « aucune suppression d'une recette validée / d'un encaissement comptabilisé ».
     */
    protected static function booted(): void
    {
        static::deleting(function (Recette $recette) {
            if ($recette->estVerrouillee()) {
                throw new \RuntimeException(
                    "Suppression interdite : la recette « {$recette->reference} » est comptabilisée ({$recette->statut})."
                );
            }
        });
    }

    public function estVerrouillee(): bool
    {
        return in_array($this->statut, self::STATUTS_VERROUILLES, true)
            || (float) $this->montant_realise > 0;
    }

    protected $fillable = [
        'exercice_id', 'prevision_recette_id', 'reference',
        'categorie_recette_id', 'source_financement_id', 'etat_membre_id',
        'rubrique_id', 'ligne_budgetaire_id', 'structure_id',
        'montant_prevu', 'montant_realise', 'statut', 'date_prevue', 'observations',
    ];

    protected $casts = [
        'montant_prevu' => 'decimal:2',
        'montant_realise' => 'decimal:2',
        'date_prevue' => 'date',
    ];

    /** Écart entre réalisé et montant retenu de la prévision. */
    protected function ecartRealisation(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->montant_realise - (float) $this->montant_prevu,
        );
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function prevision(): BelongsTo
    {
        return $this->belongsTo(PrevisionRecette::class, 'prevision_recette_id');
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

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function encaissements(): HasMany
    {
        return $this->hasMany(Encaissement::class);
    }

    /**
     * Recalcule le réalisé à partir des encaissements comptabilisés (validés / rapprochés)
     * et en déduit le statut. Source de vérité du réalisé d'une recette.
     */
    public function recalculerRealisation(): void
    {
        $realise = (float) $this->encaissements()
            ->whereIn('statut', ['valide', 'rapproche'])
            ->sum('montant_xaf');

        $prevu = (float) $this->montant_prevu;

        if ($this->statut !== 'annulee') {
            if ($realise <= 0) {
                $statut = 'prevue';
            } elseif ($realise + 0.009 >= $prevu) {
                $statut = 'realisee';
            } else {
                $statut = 'partiellement_realisee';
            }
            $this->statut = $statut;
        }

        $this->montant_realise = $realise;
        $this->save();
    }
}
