<?php

namespace App\Support;

use App\Models\User;

/** Vérifie les permissions granulaires par niveau RBM (phase 2). */
class RbmAuthorization
{
    /** @var array<string,string> */
    private const MODULES = [
        'pilier' => 'pilier',
        'axe' => 'axe',
        'produit' => 'produit',
        'sous_produit' => 'sous_produit',
        'activite' => 'activite',
        'tache' => 'tache',
    ];

    /** @var array<string,string> */
    private const ACTION_MAP = [
        'voir' => 'voir',
        'creer' => 'creer',
        'modifier' => 'modifier',
        'supprimer' => 'supprimer',
        'archiver' => 'archiver',
        'restaurer' => 'restaurer',
    ];

    public static function peut(User $user, string $niveau, string $action): bool
    {
        if ($user->can('rbm.admin')) {
            return true;
        }

        $actionGlobale = 'rbm.'.($ACTION_MAP[$action] ?? $action);
        if ($user->can($actionGlobale)) {
            return true;
        }

        if ($action === 'restaurer' && $user->can('rbm.restaurer')) {
            return true;
        }

        $module = self::MODULES[$niveau] ?? null;
        if (! $module) {
            return false;
        }

        // Permission « gerer » = créer + modifier + supprimer + archiver sur le niveau
        if ($user->can("{$module}.gerer") && in_array($action, ['creer', 'modifier', 'supprimer', 'archiver'], true)) {
            return true;
        }

        if ($user->can("{$module}.gerer") && $action === 'voir') {
            return true;
        }

        return $user->can("{$module}.{$action}");
    }
}
