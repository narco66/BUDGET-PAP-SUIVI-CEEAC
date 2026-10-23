<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contribution extends Model
{
    use Auditable;

    protected $fillable = [
        'exercice_id', 'etat_membre_id', 'source_financement_id',
        'montant_appele', 'montant_realise',
    ];

    protected $casts = [
        'montant_appele' => 'decimal:2',
        'montant_realise' => 'decimal:2',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function etatMembre(): BelongsTo
    {
        return $this->belongsTo(EtatMembre::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function previsionRecette(): HasOne
    {
        return $this->hasOne(PrevisionRecette::class);
    }
}
