<?php

namespace App\Support;

/** Normalise les agrégats PAP : montants et taux en chaînes décimales. */
final class PapMontants
{
    private const MONTANTS = [
        'montant_prevu', 'montant_paye', 'montant_engage',
        'budget_prevu', 'budget_paye', 'montant_ceeac', 'montant_ptf',
    ];

    private const TAUX = [
        'taux_physique', 'taux_financier', 'taux_financier_moyen',
        'indice_performance', 'indice_global', 'ecart',
    ];

    /** @param  array<string, mixed>  $ligne */
    public static function ligne(array $ligne): array
    {
        $ligne = Montant::champs($ligne, self::MONTANTS);
        foreach (self::TAUX as $cle) {
            if (array_key_exists($cle, $ligne)) {
                $ligne[$cle] = Montant::api($ligne[$cle], 1);
            }
        }

        return $ligne;
    }

    /** @param  iterable<int|string, mixed>  $lignes */
    public static function lignes(iterable $lignes): array
    {
        return collect($lignes)->map(function ($ligne) {
            if (! is_array($ligne)) {
                $ligne = (array) $ligne;
            }

            return self::ligne($ligne);
        })->values()->all();
    }
}
