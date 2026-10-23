<?php

namespace App\Models;

use App\Contracts\PilotableParWorkflow;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use App\Models\Concerns\HasWorkflow;
use App\Services\Reporting\RapportWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Rapport institutionnel généré : trace, historise et sécurise (hash + signature + QR) chaque
 * export produit par le module Reporting. Une nouvelle ligne par (re)génération = versioning.
 */
class Rapport extends Model implements PilotableParWorkflow
{
    use Auditable, HasGedDocuments, HasWorkflow;

    protected $fillable = [
        'reference', 'type', 'titre', 'exercice_id', 'modele_id', 'periode', 'periode_type',
        'periode_debut', 'periode_fin', 'format', 'parametres',
        'chemin', 'taille', 'hash', 'checksum_sources', 'signature', 'qr_token',
        'version', 'statut', 'statut_workflow', 'verrouille', 'ged_indexe',
        'genere_par', 'valide_par', 'valide_le', 'signe_par', 'signe_le',
        'publie_par', 'publie_le',
    ];

    protected $casts = [
        'parametres' => 'array',
        'taille' => 'integer',
        'version' => 'integer',
        'signe_le' => 'datetime',
        'valide_le' => 'datetime',
        'publie_le' => 'datetime',
        'periode_debut' => 'date',
        'periode_fin' => 'date',
        'verrouille' => 'boolean',
        'ged_indexe' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (Rapport $rapport) {
            if ($rapport->getOriginal('verrouille') && $rapport->isDirty()) {
                throw new \RuntimeException('Ce rapport est verrouillé et ne peut plus être modifié.');
            }
        });
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function generePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    public function signePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signe_par');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publie_par');
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(RapportModele::class, 'modele_id');
    }

    public function ipsas(): HasOne
    {
        return $this->hasOne(RapportIpsas::class);
    }

    public function estValide(): bool
    {
        return in_array($this->statut_workflow, ['valide', 'publie'], true);
    }

    public function estPublie(): bool
    {
        return $this->statut_workflow === 'publie';
    }

    public function estSigne(): bool
    {
        return $this->statut === 'signe' && $this->signe_par !== null;
    }

    public function estVerrouille(): bool
    {
        return (bool) $this->verrouille || $this->estSigne();
    }

    /** URL publique de vérification d'authenticité (QR). */
    public function urlVerification(): string
    {
        return route('verification.rapport', $this->qr_token);
    }

    /** Prochaine version pour un même rapport (type + exercice + période + format). */
    public static function prochaineVersion(string $type, ?int $exerciceId, ?string $periode, string $format): int
    {
        return (int) static::where('type', $type)
            ->where('exercice_id', $exerciceId)
            ->where('periode', $periode)
            ->where('format', $format)
            ->max('version') + 1;
    }

    public function auWorkflowDemarre(): void
    {
    }

    public function auEtapeValidee(int $ordre): void
    {
        if ($ordre === 1) {
            $user = auth()->user();
            $this->update([
                'statut_workflow' => 'valide',
                'valide_par' => $user?->id,
                'valide_le' => now(),
            ]);
        }
    }

    public function auWorkflowValide(): void
    {
        $user = auth()->user();
        if (! $user) {
            throw new \RuntimeException('Publication impossible : utilisateur non identifié.');
        }

        app(RapportWorkflowService::class)->publier($this->fresh(), $user);
    }

    public function auWorkflowRejete(): void
    {
        $instance = $this->workflowInstances()->latest()->first();
        $etape = $instance?->etapes()->where('statut', 'rejete')->orderByDesc('decision_le')->first();

        $this->update([
            'statut_workflow' => 'genere',
            'valide_par' => null,
            'valide_le' => null,
            'publie_par' => null,
            'publie_le' => null,
            'verrouille' => false,
        ]);
    }
}
