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

class Pap extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow
{
    use HasFactory, SoftDeletes, Auditable, HasWorkflow;

    protected $table = 'paps';

    protected $fillable = [
        'exercice_id', 'reference', 'libelle', 'structure_id', 'responsable_id',
        'date_elaboration', 'observations', 'motif_rejet', 'categorie_rejet',
        'statut', 'version', 'cree_par', 'publie_le', 'validated_at', 'validated_by',
    ];

    protected $casts = [
        'publie_le' => 'datetime',
        'validated_at' => 'datetime',
        'date_elaboration' => 'date',
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
        return $this->hasMany(PapHistorique::class)->latest();
    }

    public function piliers(): HasMany
    {
        return $this->hasMany(Pilier::class);
    }

    public function programmationsAnnuelles(): HasMany
    {
        return $this->hasMany(\App\Models\Strategie\ProgrammationAnnuelle::class);
    }

    public function risques(): HasMany
    {
        return $this->hasMany(PapRisque::class);
    }

    public function mesuresCorrectives(): HasMany
    {
        return $this->hasMany(PapMesureCorrective::class);
    }

    public function structureIdWorkflow(): ?int
    {
        return $this->structure_id;
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'rejete', 'en_revision'], true);
    }

    public function peutDemanderRevision(): bool
    {
        return $this->statut === 'publie';
    }

    public function peutEtreSupprime(): bool
    {
        if (! in_array($this->statut, ['brouillon', 'rejete'], true)) {
            return false;
        }

        return ! $this->piliers()->exists()
            && ! $this->programmationsAnnuelles()->exists();
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
            'statut' => 'publie',
            'publie_le' => now(),
            'validated_at' => now(),
            'validated_by' => auth()->id(),
        ]);
        $ref = app(\App\Services\Pap\PapReferentielService::class);
        $pap = $this->fresh();
        $ref->historiser($pap, 'publication', auth()->id());
        $ref->synchroniserLiensPiliers($pap->exercice_id);
        app(\App\Services\Pap\PapNotificationService::class)->notifierPublication($pap);
        app(\App\Services\WebhookService::class)->declencher('pap.publie', [
            'reference' => $pap->reference,
            'exercice_id' => $pap->exercice_id,
            'statut' => $pap->statut,
        ]);
    }

    public function auWorkflowRejete(): void
    {
        $this->update(['statut' => 'rejete']);
    }
}
