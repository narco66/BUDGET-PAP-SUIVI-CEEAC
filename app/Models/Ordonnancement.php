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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ordonnancement extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow
{
    use SoftDeletes, Auditable, HasWorkflow, HasPieces, ConcerneDepense;

    protected $fillable = [
        'numero', 'liquidation_id', 'montant', 'date_ordonnancement',
        'statut', 'cree_par', 'valide_par', 'valide_le', 'compteurs_appliques',
        'seuil_xaf', 'seuil_version', 'assiette_seuil', 'ordonnateur_role',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_ordonnancement' => 'date',
        'valide_le' => 'datetime',
        'compteurs_appliques' => 'boolean',
    ];

    public function colonneCompteur(): string
    {
        return 'montant_ordonnance';
    }

    public function engagementRacine(): Engagement
    {
        return $this->liquidation->engagement;
    }

    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(Liquidation::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function ordresPaiement(): HasMany
    {
        return $this->hasMany(OrdrePaiement::class);
    }

    public function auWorkflowValide(): void
    {
        $this->appliquerCompteur();
        $this->update(['statut' => 'valide', 'valide_le' => now()]);

        app(\App\Services\Depense\OrdrePaiementService::class)->emettre($this);
        app(\App\Services\Depense\HistoriqueDepenseService::class)->enregistrer($this, 'etape_validee');
        app(\App\Services\Depense\OrdonnancementVersPaiementService::class)
            ->assurerDossierDepuisOrdreSigne($this->fresh());
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
