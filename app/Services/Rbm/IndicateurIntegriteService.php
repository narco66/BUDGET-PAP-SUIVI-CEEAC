<?php

namespace App\Services\Rbm;

use App\Models\Indicateur;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use RuntimeException;

/** Phase E3 — cohérence indicateurs : catalogues type/unité obligatoires, champs legacy synchronisés. */
class IndicateurIntegriteService
{
    /** @var array<string, string> */
    public const TYPE_ENUM_PAR_CODE = [
        'STR' => 'strategique',
        'OPE' => 'operationnel',
        'FIN' => 'financier',
        'IMP' => 'impact',
    ];

    /** @var array<string, string> */
    public const CODE_PAR_TYPE_ENUM = [
        'strategique' => 'STR',
        'operationnel' => 'OPE',
        'financier' => 'FIN',
        'impact' => 'IMP',
    ];

    /** Prépare les attributs HTTP à partir des catalogues (type/unite legacy dérivés). */
    public function enrichirDepuisCatalogue(array $data): array
    {
        if (empty($data['type_indicateur_id']) || empty($data['unite_mesure_id'])) {
            throw new RuntimeException('Le type et l\'unité catalogue sont obligatoires.');
        }

        unset($data['type'], $data['unite']);

        $typeCat = TypeIndicateur::find($data['type_indicateur_id']);
        $uniteCat = UniteMesure::find($data['unite_mesure_id']);

        if (! $typeCat || ! $uniteCat) {
            throw new RuntimeException('Type ou unité catalogue introuvable.');
        }

        $data['type'] = self::TYPE_ENUM_PAR_CODE[$typeCat->code] ?? 'operationnel';
        $data['unite'] = $uniteCat->symbole ?: $uniteCat->code;

        return $data;
    }

    /** Résout les FK catalogue et synchronise les champs legacy avant persistance. */
    public function synchroniserModele(Indicateur $indicateur): void
    {
        if (! $indicateur->type_indicateur_id && $indicateur->type) {
            $code = self::CODE_PAR_TYPE_ENUM[$indicateur->type] ?? 'OPE';
            $indicateur->type_indicateur_id = TypeIndicateur::where('code', $code)->value('id');
        }

        if (! $indicateur->unite_mesure_id) {
            $indicateur->unite_mesure_id = $this->resoudreUniteMesureId($indicateur->unite);
        }

        if (! $indicateur->type_indicateur_id || ! $indicateur->unite_mesure_id) {
            throw new RuntimeException('Indicateur : type et unité catalogue obligatoires.');
        }

        $typeCat = TypeIndicateur::find($indicateur->type_indicateur_id);
        $uniteCat = UniteMesure::find($indicateur->unite_mesure_id);

        $indicateur->type = self::TYPE_ENUM_PAR_CODE[$typeCat?->code ?? ''] ?? ($indicateur->type ?: 'operationnel');
        $indicateur->unite = $uniteCat?->symbole ?: ($uniteCat?->code ?? $indicateur->unite);
    }

    public function resoudreTypeIndicateurId(?string $typeEnum): ?int
    {
        if (! $typeEnum) {
            return TypeIndicateur::where('code', 'OPE')->value('id');
        }

        $code = self::CODE_PAR_TYPE_ENUM[$typeEnum] ?? null;
        if (! $code) {
            return null;
        }

        return TypeIndicateur::where('code', $code)->value('id');
    }

    public function resoudreUniteMesureId(?string $unite): ?int
    {
        $unite = trim((string) $unite);

        if ($unite === '') {
            return UniteMesure::where('code', 'NB')->value('id');
        }

        $id = UniteMesure::query()
            ->where(fn ($q) => $q->where('symbole', $unite)->orWhere('code', strtoupper($unite)))
            ->value('id');

        return $id ?: UniteMesure::where('code', 'PCT')->value('id');
    }
}
