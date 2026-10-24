<?php

namespace App\Services\Depense;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Décomposition brut / retenues / net saisie par l’utilisateur.
 * Aucun barème fiscal n’est appliqué : seule l’identité arithmétique est contrôlée.
 */
class LiquidationMontantsService
{
    /**
     * @return array{montant:float,champs:array<string,float>}
     */
    public function depuisRequete(Request $request, float $montantDemande): array
    {
        $brut = $request->input('montant_brut');
        $retenues = $request->input('montant_retenues');
        $net = $request->input('montant_net');

        if ($brut === null && $retenues === null && $net === null) {
            return ['montant' => $montantDemande, 'champs' => []];
        }

        if ($brut === null || $retenues === null || $net === null) {
            throw new RuntimeException(
                'Brut, retenues et net doivent être saisis ensemble. Aucun barème fiscal n’est calculé.'
            );
        }

        $b = round((float) $brut, 2);
        $r = round((float) $retenues, 2);
        $n = round((float) $net, 2);
        if ($b < 0 || $r < 0 || $n < 0) {
            throw new RuntimeException('Les montants brut, retenues et net doivent être positifs ou nuls.');
        }
        if (abs(($b - $r) - $n) > 0.01) {
            throw new RuntimeException('Identité requise : net = brut − retenues (saisie manuelle, sans barème).');
        }

        return [
            'montant' => $n,
            'champs' => [
                'montant_brut' => $b,
                'montant_retenues' => $r,
                'montant_net' => $n,
            ],
        ];
    }
}
