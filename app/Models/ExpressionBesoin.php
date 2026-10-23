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
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpressionBesoin extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow
{
    use HasFactory, SoftDeletes, Auditable, HasWorkflow, HasPieces;

    protected $table = 'expressions_besoin';

    public const PRIORITES = ['haute' => 'Haute', 'moyenne' => 'Moyenne', 'basse' => 'Basse'];

    protected $fillable = [
        'numero', 'exercice_id', 'structure_id', 'activite_id', 'tache_id', 'ligne_budgetaire_id', 'demandeur_id',
        'objet', 'justification', 'montant_estime', 'priorite', 'date_besoin', 'statut',
    ];

    protected $casts = [
        'montant_estime' => 'decimal:2',
        'date_besoin' => 'date',
    ];

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

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function engagements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Engagement::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ExpressionBesoin $besoin) {
            if ($besoin->activite_id && ! $besoin->ligne_budgetaire_id) {
                $besoin->ligne_budgetaire_id = Activite::find($besoin->activite_id)?->ligne_budgetaire_id;
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
        return in_array($this->statut, ['brouillon', 'rejete'], true);
    }

    public function auWorkflowDemarre(): void { $this->update(['statut' => 'soumis']); }
    public function auEtapeValidee(int $ordre): void {}
    public function auWorkflowValide(): void { $this->update(['statut' => 'valide']); }
    public function auWorkflowRejete(): void { $this->update(['statut' => 'rejete']); }

    public function structureIdWorkflow(): ?int
    {
        return $this->structure_id;
    }
}
