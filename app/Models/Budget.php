<?php

namespace App\Models;

use App\Contracts\ConcernantStructureWorkflow;
use App\Contracts\PilotableParWorkflow;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Enveloppe budgétaire institutionnelle (1 par exercice) — cycle adoption B1. */
class Budget extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow
{
    use HasFactory, SoftDeletes, Auditable, HasWorkflow;

    protected $fillable = [
        'exercice_id', 'reference', 'libelle', 'structure_id', 'responsable_id',
        'date_elaboration', 'observations', 'motif_rejet',
        'statut', 'version', 'cree_par', 'adopte_le', 'validated_at', 'validated_by', 'montant_total',
        'programmation_pluriannuelle_id', 'programmation_annuelle_id',
    ];

    protected $casts = [
        'adopte_le' => 'datetime',
        'validated_at' => 'datetime',
        'date_elaboration' => 'date',
        'montant_total' => 'decimal:2',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(BudgetHistorique::class)->latest();
    }

    public function lignesBudgetaires(): HasMany
    {
        return $this->hasMany(LigneBudgetaire::class);
    }

    public function preparations(): HasMany
    {
        return $this->hasMany(BudgetPreparation::class);
    }

    public function arbitrages(): HasMany
    {
        return $this->hasMany(BudgetArbitrage::class)->latest();
    }

    public function consolidations(): HasMany
    {
        return $this->hasMany(BudgetConsolidation::class)->orderByDesc('version');
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(BudgetSimulation::class)->latest();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BudgetRevision::class)->latest();
    }

    public function programmationPluriannuelle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Strategie\ProgrammationPluriannuelle::class);
    }

    public function programmationAnnuelle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Strategie\ProgrammationAnnuelle::class);
    }

    public function peutPreparer(): bool
    {
        return $this->estModifiable();
    }

    public function structureIdWorkflow(): ?int
    {
        return $this->structure_id;
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'rejete', 'en_revision'], true);
    }

    public function estVerrouille(): bool
    {
        return in_array($this->statut, ['en_validation', 'adopte'], true);
    }

    public function peutDemanderRevision(): bool
    {
        return $this->statut === 'adopte';
    }

    public function peutRectifier(): bool
    {
        return in_array($this->statut, ['adopte', 'en_revision'], true);
    }

    public function peutEtreSupprime(): bool
    {
        if (! in_array($this->statut, ['brouillon', 'rejete'], true)) {
            return false;
        }

        return ! $this->workflowActif;
    }

    public function auWorkflowDemarre(): void
    {
        $this->update(['statut' => 'en_validation', 'motif_rejet' => null]);
    }

    public function auEtapeValidee(int $ordre): void
    {
        if ($ordre >= 4) {
            $this->update([
                'statut' => 'valide',
                'validated_at' => now(),
                'validated_by' => auth()->id(),
            ]);
        }
    }

    public function auWorkflowValide(): void
    {
        $this->update([
            'statut' => 'adopte',
            'adopte_le' => now(),
            'validated_at' => now(),
            'validated_by' => auth()->id(),
        ]);

        $budget = $this->fresh();
        $ref = app(\App\Services\Budget\BudgetReferentielService::class);
        $ref->historiser($budget, 'adoption', auth()->id());
        $ref->rattacherLignes($budget);
        app(\App\Services\Budget\BudgetNotificationService::class)->notifierAdoption($budget);
        app(\App\Services\WebhookService::class)->declencher('budget.adopte', [
            'reference' => $budget->reference,
            'exercice_id' => $budget->exercice_id,
            'montant_total' => (float) $budget->montant_total,
        ]);
    }

    public function auWorkflowRejete(): void
    {
        $this->update(['statut' => 'rejete']);
    }
}
