<?php

namespace App\Services\Depense;

use App\Models\ExpressionBesoin;
use App\Models\Structure;

class BesoinCircuitService
{
    public function code(ExpressionBesoin $besoin): string
    {
        if (! $besoin->estPap()) {
            return 'BESOIN_HORS_PAP';
        }

        return $this->estDirectionAppui($besoin->structure) ? 'BESOIN_PAP_APPUI' : 'BESOIN_PAP';
    }

    public function estDirectionAppui(?Structure $structure): bool
    {
        if (! $structure) {
            return false;
        }

        $types = array_map('strtolower', config('chaine_depense.types_structure_appui', []));
        $codes = array_map('strtoupper', config('chaine_depense.codes_structure_appui', []));

        if (in_array(strtolower((string) $structure->type), $types, true)) {
            return true;
        }

        if (in_array(strtoupper((string) $structure->code), $codes, true)
            || in_array(strtoupper((string) $structure->sigle), $codes, true)) {
            return true;
        }

        foreach ($structure->ancetres() as $ancetre) {
            if (in_array(strtolower((string) $ancetre->type), $types, true)
                || in_array(strtoupper((string) $ancetre->code), $codes, true)) {
                return true;
            }
        }

        return false;
    }
}
