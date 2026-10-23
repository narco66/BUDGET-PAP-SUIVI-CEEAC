<?php

namespace App\Models;

use App\Contracts\PilotableParWorkflow;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use App\Models\Concerns\HasPieces;
use App\Models\Concerns\HasWorkflow;
use App\Services\Suivi\CollecteJustificatifService;
use App\Services\Suivi\SuiviPhysiqueService;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Collecte extends Model implements PilotableParWorkflow
{
    use Auditable, HasGedDocuments, HasPieces, HasWorkflow;

    protected $fillable = [
        'campagne_id', 'activite_id', 'taux_physique', 'quantite_prevue', 'quantite_realisee', 'unite', 'date_realisation',
        'montant_realise', 'taux_financier',
        'ecart', 'cause_ecart', 'commentaire', 'statut', 'saisi_par', 'verifie_par', 'valide_par',
        'motif_rejet', 'categorie_rejet',
    ];

    protected $casts = [
        'taux_physique' => 'decimal:2',
        'quantite_prevue' => 'decimal:4',
        'quantite_realisee' => 'decimal:4',
        'date_realisation' => 'date',
        'montant_realise' => 'decimal:2',
        'taux_financier' => 'decimal:2',
        'ecart' => 'decimal:2',
    ];

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneSuivi::class, 'campagne_id');
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    /** Recalcule le taux déclaratif trimestriel et l'écart physique / financier réel. */
    public function recalculer(): void
    {
        app(SuiviPhysiqueService::class)->appliquerQuantites($this);

        $budget = (float) ($this->activite?->montant_total ?? 0);
        $this->taux_financier = $budget > 0 ? round((float) $this->montant_realise / $budget * 100, 2) : 0;

        $finCompare = $this->activite && ($this->activite->ligne_budgetaire_id || app(\App\Services\Pap\PapPerformanceService::class)->montantPayeReel($this->activite) > 0)
            ? app(\App\Services\Pap\PapPerformanceService::class)->tauxFinancierReel($this->activite)
            : (float) $this->taux_financier;

        $this->ecart = round((float) $this->taux_physique - $finCompare, 2);
    }

    /** Reporte le taux physique validé ; le taux financier provient de l'exécution budgétaire réelle. */
    public function appliquerSurActivite(): void
    {
        if (! $this->activite) {
            return;
        }

        $this->activite->update(['taux_realisation_physique' => $this->taux_physique]);

        $perf = app(\App\Services\Pap\PapPerformanceService::class);
        if (! $perf->synchroniserTauxFinancier($this->activite->fresh(['ligneBudgetaire']))) {
            $this->activite->update(['taux_realisation_financier' => $this->taux_financier]);
        }
    }

    public function auWorkflowDemarre(): void
    {
        $this->update(['motif_rejet' => null, 'categorie_rejet' => null]);
    }

    public function auEtapeValidee(int $ordre): void
    {
        if ($ordre === 1) {
            $user = auth()->user();
            $this->update([
                'statut' => 'verifiee',
                'verifie_par' => $user?->id,
            ]);
        }
    }

    public function auWorkflowValide(): void
    {
        $user = auth()->user();
        if (! $user) {
            throw new RuntimeException('Validation collecte impossible : utilisateur non identifié.');
        }

        app(CollecteJustificatifService::class)->assertPeutValider($this->fresh());

        $this->update([
            'statut' => 'validee',
            'valide_par' => $user->id,
        ]);
        $this->load('activite');
        $this->appliquerSurActivite();

        if (config('suivi.sync_taches_auto', true) && $this->activite) {
            app(SuiviPhysiqueService::class)->synchroniserTauxDepuisTaches($this->activite->fresh());
        }

        AuditLogger::enregistrer('collecte_validee', $this);
    }

    public function auWorkflowRejete(): void
    {
        $instance = $this->workflowInstances()->latest()->first();
        $etape = $instance?->etapes()->where('statut', 'rejete')->orderByDesc('decision_le')->first();

        $this->update([
            'statut' => 'saisie',
            'verifie_par' => null,
            'valide_par' => null,
            'motif_rejet' => $etape?->observation,
            'categorie_rejet' => $etape?->categorie_rejet,
        ]);
    }
}
