<?php

namespace App\Models;

use App\Contracts\PilotableParWorkflow;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use App\Models\Concerns\HasPieces;
use App\Models\Concerns\HasWorkflow;
use App\Services\Recettes\EncaissementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Encaissement extends Model implements PilotableParWorkflow
{
    use Auditable, HasGedDocuments, HasPieces, HasWorkflow, SoftDeletes;

    public const STATUTS = [
        'saisi' => 'Saisi',
        'en_validation' => 'En validation',
        'valide' => 'Validé',
        'rapproche' => 'Rapproché',
        'rejete' => 'Rejeté',
        'annule' => 'Annulé',
    ];

    public const MODES_PAIEMENT = [
        'virement' => 'Virement bancaire',
        'cheque' => 'Chèque',
        'especes' => 'Espèces',
        'compensation' => 'Compensation / contrepartie',
        'autre' => 'Autre',
    ];

    /** Statuts comptabilisés : verrouillés contre toute suppression (contrôle interne). */
    public const STATUTS_VERROUILLES = ['valide', 'rapproche'];

    protected $fillable = [
        'recette_id', 'reference', 'montant', 'devise', 'taux_change', 'montant_xaf',
        'mode_paiement', 'banque', 'reference_bancaire', 'date_encaissement',
        'statut', 'rapproche', 'date_rapprochement', 'rapproche_par',
        'saisi_par', 'valide_par', 'valide_le', 'observations',
        'motif_rejet', 'categorie_rejet',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'taux_change' => 'decimal:6',
        'montant_xaf' => 'decimal:2',
        'rapproche' => 'boolean',
        'date_encaissement' => 'date',
        'date_rapprochement' => 'date',
        'valide_le' => 'datetime',
    ];

    /** Interdit la suppression d'un encaissement validé ou rapproché (comptabilisé). */
    protected static function booted(): void
    {
        static::deleting(function (Encaissement $encaissement) {
            if ($encaissement->estVerrouille()) {
                throw new RuntimeException(
                    "Suppression interdite : l'encaissement « {$encaissement->reference} » est comptabilisé ({$encaissement->statut})."
                );
            }
        });
    }

    public function estVerrouille(): bool
    {
        return in_array($this->statut, self::STATUTS_VERROUILLES, true);
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['saisi', 'rejete'], true);
    }

    public function estComptabilise(): bool
    {
        return in_array($this->statut, ['valide', 'rapproche'], true);
    }

    public function recette(): BelongsTo
    {
        return $this->belongsTo(Recette::class);
    }

    public function ecrituresComptables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EcritureComptable::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function rapprochePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rapproche_par');
    }

    public function auWorkflowDemarre(): void
    {
        $this->update(['statut' => 'en_validation', 'motif_rejet' => null, 'categorie_rejet' => null]);
    }

    public function auEtapeValidee(int $ordre): void
    {
    }

    public function auWorkflowValide(): void
    {
        $instance = $this->workflowInstances()->latest()->first();
        $etape = $instance?->etapes()->where('statut', 'valide')->orderByDesc('ordre')->first();
        $user = $etape?->acteur ?? auth()->user();

        if (! $user) {
            throw new RuntimeException('Comptabilisation impossible : utilisateur non identifié.');
        }

        app(EncaissementService::class)->valider($this->fresh(), $user);
        $enc = $this->fresh(['recette']);
        app(\App\Services\WebhookService::class)->declencher('recette.encaissement_valide', [
            'reference' => $enc->reference,
            'recette' => $enc->recette?->reference,
            'montant_xaf' => (float) $enc->montant_xaf,
        ]);
    }

    public function auWorkflowRejete(): void
    {
        $instance = $this->workflowInstances()->latest()->first();
        $etape = $instance?->etapes()->where('statut', 'rejete')->orderByDesc('decision_le')->first();

        $this->update([
            'statut' => 'rejete',
            'motif_rejet' => $etape?->observation,
            'categorie_rejet' => $etape?->categorie_rejet,
        ]);
    }
}
