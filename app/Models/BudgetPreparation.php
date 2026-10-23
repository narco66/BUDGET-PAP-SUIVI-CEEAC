<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Proposition budgétaire par ligne — cycle préparation B2. */
class BudgetPreparation extends Model
{
    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'soumis_service' => 'Soumis (service)',
        'valide_direction' => 'Validé direction',
        'valide_departement' => 'Validé département',
        'en_arbitrage' => 'En arbitrage DPPB',
        'arbitre' => 'Arbitré',
        'consolide' => 'Consolidé',
    ];

    /** @var array<string, string> Transition autorisée par niveau validateur. */
    public const TRANSITIONS = [
        'service' => ['brouillon' => 'soumis_service'],
        'direction' => ['soumis_service' => 'valide_direction'],
        'departement' => ['valide_direction' => 'valide_departement'],
        'dppb' => ['valide_departement' => 'en_arbitrage'],
    ];

    protected $fillable = [
        'budget_id', 'ligne_budgetaire_id', 'structure_id', 'activite_id', 'reference',
        'montant_demande', 'montant_propose', 'montant_arbitre',
        'statut', 'observations', 'soumis_le', 'valide_le', 'valide_par',
    ];

    protected $casts = [
        'montant_demande' => 'decimal:2',
        'montant_propose' => 'decimal:2',
        'montant_arbitre' => 'decimal:2',
        'soumis_le' => 'datetime',
        'valide_le' => 'datetime',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'soumis_service'], true)
            && $this->budget?->estModifiable();
    }

    public function montantEffectif(): float
    {
        return (float) ($this->montant_arbitre ?? $this->montant_propose ?? $this->montant_demande);
    }
}
