<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcritureComptable extends Model
{
    protected $table = 'ecritures_comptables';

    public const COMPTES = [
        'banque' => '521000',
        'fournisseur' => '401000',
        'charges_prefixe' => '6',
        'recettes_prefixe' => '7',
    ];

    protected $fillable = [
        'exercice_id', 'paiement_id', 'encaissement_id', 'engagement_id', 'journal', 'numero_piece', 'date_ecriture',
        'compte_debit', 'compte_credit', 'montant', 'devise', 'libelle', 'reference', 'statut',
        'exportee_le', 'metadata',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_ecriture' => 'date',
        'exportee_le' => 'datetime',
        'metadata' => 'array',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function encaissement(): BelongsTo
    {
        return $this->belongsTo(Encaissement::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
