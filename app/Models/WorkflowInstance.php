<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowInstance extends Model
{
    protected $table = 'workflow_instances';

    protected $fillable = [
        'workflow_id', 'workflow_version_id', 'objet_type', 'objet_id', 'objet_version',
        'statut', 'etape_courante', 'demarre_par',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function objet(): MorphTo
    {
        return $this->morphTo();
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(WorkflowInstanceEtape::class, 'instance_id')->orderBy('ordre');
    }

    public function demarreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demarre_par');
    }

    /** Première étape en attente à l'ordre courant (compatibilité séquentielle). */
    public function etapeCourante(): ?WorkflowInstanceEtape
    {
        return $this->etapes()
            ->where('ordre', $this->etape_courante)
            ->where('statut', 'en_attente')
            ->orderBy('id')
            ->first();
    }

    /** Étape en attente que $user peut traiter (y compris groupe parallèle). */
    public function etapePourUtilisateur(User $user): ?WorkflowInstanceEtape
    {
        foreach ($this->etapes()
            ->whereIn('ordre', $this->ordresDuGroupeCourant())
            ->where('statut', 'en_attente')
            ->orderBy('ordre')
            ->get() as $etape) {
            if ($user->hasRole('administrateur') || $user->hasRole($etape->role)) {
                return $etape;
            }
        }

        return null;
    }

    public function ordreCourantTermine(): bool
    {
        return ! $this->etapes()
            ->whereIn('ordre', $this->ordresDuGroupeCourant())
            ->where('statut', '!=', 'valide')
            ->exists();
    }

    /** @return list<int> */
    public function ordresDuGroupeCourant(): array
    {
        $workflow = $this->relationLoaded('workflow')
            ? $this->workflow
            : $this->workflow()->with('etapes')->first();

        $template = $workflow?->etapes->firstWhere('ordre', $this->etape_courante);
        if ($template?->groupe_parallele && ($template->type_approbation ?? 'sequentiel') === 'parallele') {
            return $workflow->etapes
                ->where('groupe_parallele', $template->groupe_parallele)
                ->pluck('ordre')
                ->map(fn ($o) => (int) $o)
                ->values()
                ->all();
        }

        return [(int) $this->etape_courante];
    }

    public function prochainOrdreApresGroupe(): int
    {
        return max($this->ordresDuGroupeCourant()) + 1;
    }
}
