<?php

namespace App\Services\Import\Support;

use App\Models\Exercice;
use App\Models\ImportLot;
use App\Models\User;

/**
 * État partagé pendant l'analyse ou l'exécution d'un lot : lot, exercice, mode, utilisateur, caches de
 * référentiels et entités prévues dans le même lot (un parent peut figurer dans le même fichier).
 */
final class ContexteImport
{
    /** @var array<string, mixed> caches propres à chaque domaine */
    public array $cache = [];

    /** @var array<string, array<string, array<string, mixed>>> entités prévues dans le lot : [catégorie][clé] => attributs */
    private array $prevues = [];

    public function __construct(
        public readonly ImportLot $lot,
        public readonly ?Exercice $exercice,
        public readonly string $mode,
        public readonly ?User $user,
    ) {}

    public function miseAJourAutorisee(): bool
    {
        return $this->mode === 'mise_a_jour';
    }

    /** @param array<string, mixed> $attributs */
    public function prevoir(string $categorie, string $cle, array $attributs = []): void
    {
        $this->prevues[$categorie][$cle] = $attributs;
    }

    /** @return array<string, mixed>|null */
    public function prevue(string $categorie, string $cle): ?array
    {
        return $this->prevues[$categorie][$cle] ?? null;
    }
}
