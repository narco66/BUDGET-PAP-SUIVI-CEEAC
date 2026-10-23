<?php

namespace App\Support;

use App\Rbm\Niveaux;

/** Filtre d'affichage par statut référentiel (phase E4). */
class RbmStatutFiltre
{
    /** @var list<string> */
    public const FILTRES = ['actif', 'inactif', 'archive', 'tous'];

    public static function normaliser(?string $filtre): string
    {
        return in_array($filtre, self::FILTRES, true) ? $filtre : 'actif';
    }

    public static function statut(object $noeud, string $niveau): string
    {
        $col = $niveau === 'tache' ? 'statut_referentiel' : 'statut';

        return $noeud->{$col} ?? 'actif';
    }

    /** Nœud affiché si lui-même ou un descendant correspond au filtre (archive/inactif). */
    public static function visible(object $noeud, string $niveau, string $filtre): bool
    {
        if ($filtre === 'tous') {
            return true;
        }

        $statut = self::statut($noeud, $niveau);

        if ($filtre === 'actif') {
            return $statut === 'actif';
        }

        if ($statut === $filtre) {
            return true;
        }

        return self::descendantVisible($noeud, $niveau, $filtre);
    }

    private static function descendantVisible(object $noeud, string $niveau, string $filtre): bool
    {
        $config = Niveaux::get($niveau);
        $relation = $config['relation_enfants'] ?? null;
        $enfantNiveau = $config['enfant_niveau'] ?? null;

        if (! $relation || ! $enfantNiveau || ! method_exists($noeud, $relation)) {
            return false;
        }

        foreach ($noeud->{$relation} as $enfant) {
            if (self::visible($enfant, $enfantNiveau, $filtre)) {
                return true;
            }
        }

        return false;
    }

    public static function badgeClass(string $statut): string
    {
        return match ($statut) {
            'actif' => 'bg-success',
            'archive' => 'bg-secondary',
            'inactif' => 'bg-warning text-dark',
            default => 'bg-light text-dark',
        };
    }
}
