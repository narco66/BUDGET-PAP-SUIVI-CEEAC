<?php

namespace App\Services\Ged;

use App\Models\Ged\GedDocument;
use Illuminate\Support\Facades\DB;

class GedReferenceService
{
    /** Génère une référence : CEEAC/GED/{MODULE}/{TYPE}/{ANNEE}/{NUMERO} */
    public function generer(string $module, string $type, ?int $annee = null): string
    {
        $annee = $annee ?? (int) now()->format('Y');
        $module = strtoupper(preg_replace('/[^A-Z0-9_]/i', '', $module) ?: 'GED');
        $type = strtoupper(preg_replace('/[^A-Z0-9_]/i', '', $type) ?: 'DOC');

        $prefixe = "CEEAC/GED/{$module}/{$type}/{$annee}/";

        $dernier = GedDocument::withTrashed()
            ->where('reference', 'like', $prefixe.'%')
            ->orderByDesc('id')
            ->value('reference');

        $numero = 1;
        if ($dernier && preg_match('/\/(\d+)$/', $dernier, $m)) {
            $numero = (int) $m[1] + 1;
        }

        return $prefixe.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }
}
