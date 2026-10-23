<?php

namespace App\Models\Strategie;

use App\Contracts\PilotableParWorkflow;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPieces;
use App\Models\Concerns\HasWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanStrategique extends Model implements PilotableParWorkflow
{
    use SoftDeletes, Auditable, HasPieces, HasWorkflow;

    protected $table = 'plans_strategiques';

    protected $fillable = [
        'reference', 'intitule', 'vision_id', 'date_debut', 'date_fin',
        'statut', 'version', 'description', 'document_officiel',
        'submitted_at', 'validated_at', 'validated_by', 'motif_rejet', 'categorie_rejet',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'version' => 'integer',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function vision(): BelongsTo
    {
        return $this->belongsTo(Vision::class);
    }

    public function orientations(): HasMany
    {
        return $this->hasMany(OrientationStrategique::class)->orderBy('ordre');
    }

    public function objectifs(): HasMany
    {
        return $this->hasMany(ObjectifStrategique::class)->orderBy('ordre');
    }

    public function programmationsPluriannuelles(): HasMany
    {
        return $this->hasMany(ProgrammationPluriannuelle::class);
    }

    public function risques(): HasMany
    {
        return $this->hasMany(RisqueStrategique::class);
    }

    public function alignements(): HasMany
    {
        return $this->hasMany(AlignementStrategique::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function auWorkflowDemarre(): void
    {
        $this->update([
            'statut' => 'en_validation',
            'submitted_at' => now(),
            'motif_rejet' => null,
            'categorie_rejet' => null,
        ]);
    }

    public function auEtapeValidee(int $ordre): void
    {
    }

    public function auWorkflowValide(): void
    {
        $user = auth()->user();
        $this->update([
            'statut' => 'actif',
            'validated_at' => now(),
            'validated_by' => $user?->id,
            'motif_rejet' => null,
            'categorie_rejet' => null,
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
