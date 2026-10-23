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

class Liquidation extends Model implements PilotableParWorkflow, ConcernantStructureWorkflow
{
    use SoftDeletes, Auditable, HasWorkflow, HasPieces, ConcerneDepense;

    protected $fillable = [
        'numero', 'engagement_id', 'montant', 'date_liquidation', 'service_fait',
        'statut', 'cree_par', 'valide_par', 'valide_le', 'compteurs_appliques',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_liquidation' => 'date',
        'valide_le' => 'datetime',
        'compteurs_appliques' => 'boolean',
    ];

    public function colonneCompteur(): string
    {
        return 'montant_liquide';
    }

    public function engagementRacine(): Engagement
    {
        return $this->engagement;
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function ordonnancements(): HasMany
    {
        return $this->hasMany(Ordonnancement::class);
    }

    public function servicesFaits(): HasMany
    {
        return $this->hasMany(ServiceFait::class);
    }

    public function piecesLiquidation(): HasMany
    {
        return $this->hasMany(LiquidationPiece::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
