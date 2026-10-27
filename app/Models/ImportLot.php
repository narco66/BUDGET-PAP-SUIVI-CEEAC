<?php

namespace App\Models;

use App\Contracts\PilotableParWorkflow;
use App\Models\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot d'import : un fichier, un domaine, un mode, et son cycle contrôlé
 * (analyse → correspondance → validation → simulation → approbation → exécution).
 * L'approbation des lots sensibles passe par le moteur de workflow (tâche « Mes Tâches » + notification).
 */
class ImportLot extends Model implements PilotableParWorkflow
{
    use HasWorkflow;

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'fichier_charge' => 'Fichier chargé',
        'mapping' => 'Correspondance à compléter',
        'anomalies_detectees' => 'Anomalies bloquantes',
        'simule' => 'Simulé — prêt',
        'en_attente_approbation' => 'En attente d\'approbation',
        'approuve' => 'Approuvé',
        'en_cours' => 'Exécution en cours',
        'termine' => 'Terminé',
        'echec' => 'Échec',
        'rejete' => 'Rejeté',
        'annule' => 'Annulé',
    ];

    /** États définitifs : le lot est immuable ; toute correction passe par un nouveau lot. */
    public const FINAUX = ['termine', 'echec', 'rejete', 'annule'];

    public const MODES = [
        'insertion' => 'Insertion uniquement',
        'mise_a_jour' => 'Insertion et mise à jour contrôlée',
        'simulation' => 'Simulation (aucune écriture)',
    ];

    protected $fillable = [
        'reference', 'domaine', 'mode', 'exercice_id', 'statut',
        'fichier_nom', 'fichier_chemin', 'fichier_empreinte', 'fichier_taille', 'fichier_type', 'feuille',
        'entetes', 'mapping', 'totaux', 'progression', 'motif', 'cree_par',
        'analyse_le', 'soumis_le', 'approuve_par', 'approuve_le', 'execute_par', 'execute_le', 'termine_le', 'version',
    ];

    protected $casts = [
        'entetes' => 'array',
        'mapping' => 'array',
        'totaux' => 'array',
        'analyse_le' => 'datetime',
        'soumis_le' => 'datetime',
        'approuve_le' => 'datetime',
        'execute_le' => 'datetime',
        'termine_le' => 'datetime',
        'progression' => 'integer',
        'version' => 'integer',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }

    public function executant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execute_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(ImportLigne::class);
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(ImportAnomalie::class);
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(ImportEvenement::class)->orderBy('created_at')->orderBy('id');
    }

    public function estFinal(): bool
    {
        return in_array($this->statut, self::FINAUX, true);
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /** @return int|float|null */
    public function total(string $cle)
    {
        return $this->totaux[$cle] ?? 0;
    }

    // ── Moteur de workflow : l'approbation d'un lot sensible ──────────────────────────────────

    public function auWorkflowDemarre(): void
    {
        $this->update(['statut' => 'en_attente_approbation', 'soumis_le' => now()]);
    }

    public function auEtapeValidee(int $ordre): void
    {
    }

    public function auWorkflowValide(): void
    {
        $this->update(['statut' => 'approuve', 'approuve_par' => auth()->id(), 'approuve_le' => now()]);
        app(\App\Services\Import\ImportLotService::class)->journaliser($this, 'approbation', 'en_attente_approbation', 'approuve', 'Lot approuvé.');
    }

    public function auWorkflowRejete(): void
    {
        $etape = $this->workflowInstances()->latest('id')->first()?->etapes()->where('statut', 'rejete')->latest('decision_le')->first();
        $this->update(['statut' => 'rejete', 'motif' => $etape?->observation, 'termine_le' => now()]);
        app(\App\Services\Import\ImportLotService::class)->journaliser($this, 'rejet', 'en_attente_approbation', 'rejete', $etape?->observation);
    }
}
