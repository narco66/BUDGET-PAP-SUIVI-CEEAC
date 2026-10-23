<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapprochementBancaire extends Model
{
    protected $table = 'rapprochements_bancaires';

    protected $fillable = [
        'exercice_id', 'reference', 'intitule', 'compte_bancaire', 'date_debut', 'date_fin',
        'montant_releve', 'montant_rapproche', 'statut', 'cree_par', 'cloture_le',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'montant_releve' => 'decimal:2',
        'montant_rapproche' => 'decimal:2',
        'cloture_le' => 'datetime',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(RapprochementLigne::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }
}
