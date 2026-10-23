<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DecaissementConvention extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'decaissements_convention';

    public const STATUTS = [
        'prevu' => 'Prévu',
        'execute' => 'Exécuté',
        'annule' => 'Annulé',
    ];

    protected $fillable = [
        'convention_id', 'activite_id', 'reference', 'objet',
        'montant', 'devise', 'taux_change', 'montant_xaf',
        'date_decaissement', 'statut', 'observations', 'saisi_par',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'taux_change' => 'decimal:6',
        'montant_xaf' => 'decimal:2',
        'date_decaissement' => 'date',
    ];

    public function convention(): BelongsTo
    {
        return $this->belongsTo(ConventionFinancement::class, 'convention_id');
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
