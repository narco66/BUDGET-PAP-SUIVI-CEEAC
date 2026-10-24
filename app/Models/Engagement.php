<?php

namespace App\Models;

use App\Contracts\ConcernantStructureWorkflow;
use App\Contracts\PilotableParWorkflow;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPieces;
use App\Models\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Engagement extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow
{
    use HasFactory, SoftDeletes, Auditable, HasWorkflow, HasPieces;

    protected $fillable = [
        'numero', 'exercice_id', 'expression_besoin_id', 'activite_id', 'tache_id', 'type', 'objet', 'beneficiaire',
        'montant_total', 'date_engagement', 'statut', 'cree_par', 'controleur_id', 'vise_le', 'credits_reserves',
        'diagnostic_credits', 'chaine_cloturee_le',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'date_engagement' => 'date',
        'vise_le' => 'datetime',
        'credits_reserves' => 'boolean',
        'diagnostic_credits' => 'array',
        'chaine_cloturee_le' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function expressionBesoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class);
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(EngagementImputation::class);
    }

    public function liquidations(): HasMany
    {
        return $this->hasMany(Liquidation::class);
    }

    public function reservationsCredits(): HasMany
    {
        return $this->hasMany(ReservationCredit::class);
    }

    public function certificats(): HasMany
    {
        return $this->hasMany(CertificatEngagement::class);
    }

    /** Montant déjà couvert par des liquidations non rejetées/annulées. */
    public function montantLiquideComptabilise(?int $ignoreLiquidationId = null): float
    {
        $q = $this->liquidations()->whereNotIn('statut', ['rejete']);
        if ($ignoreLiquidationId) {
            $q->where('id', '!=', $ignoreLiquidationId);
        }

        return (float) $q->sum('montant');
    }

    /** Solde liquidable restant sur l'engagement. */
    public function montantRestantLiquidation(?int $ignoreLiquidationId = null): float
    {
        return max(0, round((float) $this->montant_total - $this->montantLiquideComptabilise($ignoreLiquidationId), 2));
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function controleur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controleur_id');
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'rejete', 'en_attente_arbitrage'], true);
    }

    /**
     * Réserve les crédits : contrôle la disponibilité de CHAQUE ligne imputée
     * puis incrémente atomiquement leurs compteurs « engagé ». Tout ou rien.
     */
    public function reserverCredits(): void
    {
        if ($this->credits_reserves) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->imputations()->with('ligneBudgetaire')->get() as $imp) {
                /** @var LigneBudgetaire $ligne */
                $ligne = LigneBudgetaire::lockForUpdate()->find($imp->ligne_budgetaire_id);

                if (! $ligne || ! $ligne->peutEngager((float) $imp->montant)) {
                    throw new RuntimeException(
                        "Crédits insuffisants sur la ligne {$ligne?->code} (disponible : ".
                        number_format((float) ($ligne?->disponible ?? 0), 0, ',', ' ').' XAF).'
                    );
                }

                $ligne->increment('montant_engage', (float) $imp->montant);

                $this->reservationsCredits()->create([
                    'ligne_budgetaire_id' => $ligne->id,
                    'montant' => (float) $imp->montant,
                    'statut' => 'active',
                ]);
            }

            $this->update(['credits_reserves' => true, 'diagnostic_credits' => null]);
        });
    }

    /** Libère les crédits réservés (rejet / annulation). */
    public function libererCredits(): void
    {
        if (! $this->credits_reserves) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->imputations as $imp) {
                LigneBudgetaire::whereKey($imp->ligne_budgetaire_id)
                    ->decrement('montant_engage', (float) $imp->montant);
            }

            $this->reservationsCredits()->where('statut', 'active')->update([
                'statut' => 'liberee',
                'liberee_le' => now(),
            ]);

            $this->update(['credits_reserves' => false]);
        });
    }

    // --- Réactions au workflow (visa du contrôleur financier) ---

    public function auWorkflowDemarre(): void { $this->update(['statut' => 'soumis']); }

    public function auEtapeValidee(int $ordre): void {}

    public function auWorkflowValide(): void
    {
        $this->update(['statut' => 'vise', 'vise_le' => now()]);

        app(\App\Services\Depense\CertificatEngagementService::class)
            ->enregistrer($this, 'engagement');
        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($this, 'engagement_vise');
        app(\App\Services\Depense\EngagementVersLiquidationService::class)
            ->assurerDepuisEngagementVise($this->fresh());
    }

    public function auWorkflowRejete(): void
    {
        $this->libererCredits();
        $this->update(['statut' => 'rejete']);
        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($this, 'engagement_rejete');
    }

    public function structureIdWorkflow(): ?int
    {
        if ($this->relationLoaded('expressionBesoin')) {
            return $this->expressionBesoin?->structure_id;
        }

        return $this->expressionBesoin()->value('structure_id');
    }
}
