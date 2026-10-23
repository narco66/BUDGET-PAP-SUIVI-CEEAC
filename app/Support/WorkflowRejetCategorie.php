<?php

namespace App\Support;

/** Catégories institutionnelles de rejet workflow (P0). */
final class WorkflowRejetCategorie
{
    public const ADMINISTRATIF = 'administratif';

    public const BUDGETAIRE = 'budgetaire';

    public const TECHNIQUE = 'technique';

    public const FINANCIER = 'financier';

    public const DOCUMENTAIRE = 'documentaire';

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::ADMINISTRATIF,
            self::BUDGETAIRE,
            self::TECHNIQUE,
            self::FINANCIER,
            self::DOCUMENTAIRE,
        ];
    }

    /** @return array<string, string> */
    public static function libelles(): array
    {
        return [
            self::ADMINISTRATIF => 'Administratif',
            self::BUDGETAIRE => 'Budgétaire',
            self::TECHNIQUE => 'Technique',
            self::FINANCIER => 'Financier',
            self::DOCUMENTAIRE => 'Documentaire',
        ];
    }
}
