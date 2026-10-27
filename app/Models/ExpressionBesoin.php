<?php

namespace App\Models;

use App\Contracts\ConcernantStructureWorkflow;
use App\Contracts\PilotableParWorkflow;
use App\Contracts\RenvoiAuDemandeur;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPieces;
use App\Models\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpressionBesoin extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow, RenvoiAuDemandeur
{
    use HasFactory, SoftDeletes, Auditable, HasWorkflow, HasPieces;

    protected $table = 'expressions_besoin';

    public const PRIORITES = ['haute' => 'Haute', 'moyenne' => 'Moyenne', 'basse' => 'Basse'];

    protected $fillable = [
        'numero', 'categorie', 'exercice_id', 'structure_id', 'activite_id', 'tache_id', 'ligne_budgetaire_id', 'ligne_detail_id', 'demandeur_id',
        'objet', 'justification', 'montant_estime', 'priorite', 'date_besoin', 'statut',
        'heritage_pap', 'alerte_credits',
    ];

    protected $casts = [
        'montant_estime' => 'decimal:2',
        'date_besoin' => 'date',
        'heritage_pap' => 'array',
        'alerte_credits' => 'array',
    ];

    public function estPap(): bool
    {
        return ($this->categorie ?? 'pap') !== 'hors_pap';
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class);
    }

    /** Détail à 5 chiffres du budget de fonctionnement choisi (besoin hors PAP). */
    public function ligneDetail(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaireDetail::class, 'ligne_detail_id');
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function engagements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Engagement::class);
    }

    public function engagement(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Engagement::class);
    }

    public function lignes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExpressionBesoinLigne::class)->orderBy('ordre');
    }

    /** Tâches de l'activité exprimée par ce besoin (saisies en sous-lignes de la ligne PAP). */
    public function tachesActivite(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tache::class, 'expression_besoin_id')->orderBy('ordre');
    }

    protected static function booted(): void
    {
        static::deleting(function (ExpressionBesoin $besoin) {
            if ($besoin->engagements()->exists()) {
                throw new \RuntimeException('Annulation de l’EB interdite : un engagement lié existe. Annuler d’abord l’engagement et ses effets.');
            }
        });

        static::saving(function (ExpressionBesoin $besoin) {
            if ($besoin->exists && $besoin->isDirty('categorie') && ($besoin->getOriginal('statut') ?? 'brouillon') !== 'brouillon') {
                throw new \RuntimeException('La catégorie PAP / hors PAP ne peut plus être modifiée après la première soumission.');
            }

            if ($besoin->activite_id && ! $besoin->ligne_budgetaire_id && ($activite = Activite::find($besoin->activite_id))) {
                // Rattachement explicite de l'activité, sinon enveloppe PAP de son axe pour l'exercice du besoin.
                $besoin->ligne_budgetaire_id = $activite->ligne_budgetaire_id
                    ?? app(\App\Services\Depense\ImputationPapService::class)->ligneDe($activite, $besoin->exercice_id)?->id;
            }

            if ($besoin->tache_id && $besoin->activite_id) {
                $tacheActivite = Tache::find($besoin->tache_id)?->activite_id;
                if ($tacheActivite && (int) $tacheActivite !== (int) $besoin->activite_id) {
                    $besoin->tache_id = null;
                }
            }
        });
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'rejete', 'a_corriger'], true);
    }

    public function auWorkflowDemarre(): void { $this->update(['statut' => 'soumis']); }
    public function auWorkflowValide(): void
    {
        $this->update(['statut' => 'valide']);
        app(\App\Services\Depense\BesoinVersEngagementService::class)
            ->assurerDepuisBesoinApprouve($this->fresh(), $this->demandeur);
    }
    public function auWorkflowRejete(): void { $this->update(['statut' => 'rejete']); }

    public function auWorkflowRenvoye(): void
    {
        $this->update(['statut' => 'a_corriger']);
    }

    public function enCorrection(): bool
    {
        return $this->statut === 'a_corriger';
    }

    public function auCircuitRepris(): void
    {
        $this->update(['statut' => 'soumis']);
    }

    public function auEtapeValidee(int $ordre): void
    {
        if ($this->statut === 'a_corriger') {
            $this->update(['statut' => 'soumis']);
        }
    }

    public function structureIdWorkflow(): ?int
    {
        return $this->structure_id;
    }
}
