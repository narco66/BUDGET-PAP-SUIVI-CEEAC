<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcheanceApurement extends Model
{
    use Auditable;

    protected $table = 'echeances_apurement';

    public const STATUTS = [
        'a_venir' => 'À venir',
        'partielle' => 'Partiellement réglée',
        'honoree' => 'Honorée',
    ];

    protected $fillable = [
        'plan_apurement_id', 'rang', 'date_echeance',
        'montant_prevu', 'montant_regle', 'statut', 'date_reglement',
    ];

    protected $casts = [
        'rang' => 'integer',
        'date_echeance' => 'date',
        'montant_prevu' => 'decimal:2',
        'montant_regle' => 'decimal:2',
        'date_reglement' => 'date',
    ];

    public function solde(): float
    {
        return max((float) $this->montant_prevu - (float) $this->montant_regle, 0);
    }

    public function enRetard(): bool
    {
        return $this->statut !== 'honoree' && $this->date_echeance?->isPast();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanApurement::class, 'plan_apurement_id');
    }
}
