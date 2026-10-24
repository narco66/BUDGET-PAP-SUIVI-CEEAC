<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressionBesoinLigne extends Model
{
    protected $table = 'expression_besoin_lignes';

    protected $fillable = [
        'expression_besoin_id', 'ordre', 'libelle', 'quantite', 'unite', 'prix_unitaire', 'taxe', 'montant',
    ];

    protected $casts = [
        'quantite' => 'decimal:4',
        'prix_unitaire' => 'decimal:2',
        'taxe' => 'decimal:2',
        'montant' => 'decimal:2',
    ];

    public function besoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class, 'expression_besoin_id');
    }

    public static function calculerMontant(float $quantite, float $prixUnitaire, float $taxe = 0): string
    {
        $ht = round($quantite * $prixUnitaire, 2);

        return number_format($ht + $taxe, 2, '.', '');
    }
}
