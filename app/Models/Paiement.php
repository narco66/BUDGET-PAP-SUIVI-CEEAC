<?php

namespace App\Models;

use App\Contracts\PilotableParWorkflow;
use App\Contracts\ConcernantStructureWorkflow;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ConcerneDepense;
use App\Models\Concerns\HasPieces;
use App\Models\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paiement extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow
{
    use SoftDeletes, Auditable, HasWorkflow, HasPieces, ConcerneDepense;

    protected $fillable = [
        'numero', 'ordonnancement_id', 'ordre_paiement_id', 'rapprochement_bancaire_id', 'montant', 'devise', 'taux_change', 'montant_xaf',
        'date_paiement', 'mode_paiement', 'reference_bancaire', 'rib', 'rib_modifie_par', 'motif_partiel', 'statut', 'etat_execution', 'cree_par', 'valide_par', 'valide_le', 'compteurs_appliques',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'taux_change' => 'decimal:6',
        'montant_xaf' => 'decimal:2',
        'date_paiement' => 'date',
        'valide_le' => 'datetime',
        'compteurs_appliques' => 'boolean',
    ];

    public function colonneCompteur(): string
    {
        return 'montant_paye';
    }

    public function engagementRacine(): Engagement
    {
        return $this->ordonnancement->liquidation->engagement;
    }

    public function ordonnancement(): BelongsTo
    {
        return $this->belongsTo(Ordonnancement::class);
    }

    public function ordrePaiement(): BelongsTo
    {
        return $this->belongsTo(OrdrePaiement::class);
    }

    public function rapprochementBancaire(): BelongsTo
    {
        return $this->belongsTo(RapprochementBancaire::class);
    }

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaiementDetail::class);
    }

    public function rejets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaiementRejet::class);
    }

    public function ecrituresComptables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EcritureComptable::class);
    }

    /** Montant en XAF pour les compteurs budgétaires (phase D4). */
    public function montantPourCompteurs(): float
    {
        return (float) ($this->montant_xaf ?? $this->montant);
    }

    public function auWorkflowValide(): void
    {
        $this->appliquerCompteur();
        $this->update([
            'statut' => 'valide',
            'valide_le' => now(),
            'etat_execution' => 'execute',
        ]);

        if ($this->ordre_paiement_id) {
            OrdrePaiement::whereKey($this->ordre_paiement_id)
                ->where('statut', 'emis')
                ->update(['statut' => 'execute']);
        }

        app(\App\Services\Depense\PaiementDetailService::class)->genererDepuisEngagement($this);
        app(\App\Services\Depense\EcritureComptableService::class)->genererPourPaiement($this->fresh());
        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($this, 'paiement_valide');
        app(\App\Services\Depense\ChaineClotureService::class)->assurer($this->engagementRacine());
    }

    public function auWorkflowRejete(): void
    {
        $this->update(['statut' => 'rejete']);
        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($this, 'paiement_rejete');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
