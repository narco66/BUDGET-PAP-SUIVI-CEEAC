<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanApurement extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'plans_apurement';

    public const STATUTS = [
        'actif' => 'Actif',
        'solde' => 'Soldé',
        'annule' => 'Annulé',
    ];

    protected $fillable = [
        'appel_fonds_id', 'reference', 'montant_total', 'nombre_echeances',
        'date_debut', 'statut', 'observations', 'etabli_par',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'nombre_echeances' => 'integer',
        'date_debut' => 'date',
    ];

    public function estVerrouille(): bool
    {
        return $this->echeances()->where('montant_regle', '>', 0)->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (PlanApurement $plan) {
            if ($plan->estVerrouille()) {
                throw new \RuntimeException("Suppression interdite : le plan « {$plan->reference} » a des règlements enregistrés.");
            }
        });
    }

    public function montantRegle(): float
    {
        return (float) $this->echeances()->sum('montant_regle');
    }

    public function solde(): float
    {
        return max((float) $this->montant_total - $this->montantRegle(), 0);
    }

    /** En défaut : au moins une échéance échue non honorée. */
    public function enDefaut(): bool
    {
        return $this->statut === 'actif' && $this->echeances()
            ->where('statut', '!=', 'honoree')
            ->whereDate('date_echeance', '<', now())
            ->exists();
    }

    public function appel(): BelongsTo
    {
        return $this->belongsTo(AppelFonds::class, 'appel_fonds_id');
    }

    public function echeances(): HasMany
    {
        return $this->hasMany(EcheanceApurement::class, 'plan_apurement_id')->orderBy('rang');
    }

    public function etabliPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etabli_par');
    }
}
