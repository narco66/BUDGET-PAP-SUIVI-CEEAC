<?php

namespace App\Services\Depense;

use App\Models\Liquidation;
use App\Models\LiquidationPiece;
use App\Support\Montant;

/** Alerte métier : facture déjà rencontrée. Ne bloque pas (contrôle humain). */
class FactureDoublonService
{
    /** @return array<string, mixed>|null */
    public function alerte(Liquidation $liquidation): ?array
    {
        $reference = trim((string) ($liquidation->numero_facture ?: ''));
        if ($reference === '') {
            $piece = $liquidation->piecesLiquidation()->where('type', 'facture')->orderByDesc('id')->first();
            $reference = trim((string) ($piece?->reference ?: ''));
        }
        if ($reference === '') {
            return null;
        }

        $date = $liquidation->date_facture?->toDateString();
        $montant = Montant::api($liquidation->montant);

        $pieces = LiquidationPiece::query()
            ->where('type', 'facture')
            ->where('reference', $reference)
            ->where('liquidation_id', '!=', $liquidation->id)
            ->with('liquidation')
            ->get();

        $autres = Liquidation::query()
            ->where('id', '!=', $liquidation->id)
            ->where('numero_facture', $reference)
            ->whereNotIn('statut', ['rejete', 'annule'])
            ->get();

        $ids = $pieces->pluck('liquidation_id')->merge($autres->pluck('id'))->unique()->filter();
        if ($ids->isEmpty()) {
            return null;
        }

        return [
            'message' => 'Alerte : une facture de même numéro existe déjà. Vérifier l’absence de double liquidation.',
            'numero_facture' => $reference,
            'date_facture' => $date,
            'montant' => $montant,
            'dossiers' => $ids->values()->all(),
        ];
    }
}
