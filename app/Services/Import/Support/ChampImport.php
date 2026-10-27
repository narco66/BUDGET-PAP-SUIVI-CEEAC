<?php

namespace App\Services\Import\Support;

/** Champ métier attendu par un domaine d'import : nom, libellé, type de normalisation et synonymes d'en-tête. */
final class ChampImport
{
    public const TYPES = ['texte', 'code', 'entier', 'montant', 'decimal', 'date', 'booleen', 'email'];

    /** @param list<string> $synonymes en-têtes reconnus automatiquement (comparés sans accents ni casse) */
    public function __construct(
        public readonly string $champ,
        public readonly string $libelle,
        public readonly bool $requis = false,
        public readonly string $type = 'texte',
        public readonly array $synonymes = [],
        public readonly ?string $aide = null,
    ) {}
}
