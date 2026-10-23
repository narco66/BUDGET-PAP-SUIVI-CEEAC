<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Génère un numéro séquentiel par exercice : PREFIXE-ANNEE-0001. */
class Numero
{
    public static function suivant(string $table, string $prefixe, int $annee, string $colonne = 'numero'): string
    {
        $compte = DB::table($table)->where($colonne, 'like', "{$prefixe}-{$annee}-%")->count();

        return sprintf('%s-%d-%04d', $prefixe, $annee, $compte + 1);
    }
}
