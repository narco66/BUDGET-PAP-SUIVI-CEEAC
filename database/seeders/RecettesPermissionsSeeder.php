<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase R2-A — permissions du module « Gestion des Recettes » (non destructif).
 * Module `recette` × 9 actions ; affectation aux rôles métier (séparation des fonctions).
 */
class RecettesPermissionsSeeder extends Seeder
{
    /** @var array<string,string> action => libellé */
    private array $actions = [
        'voir' => 'Voir',
        'creer' => 'Créer',
        'modifier' => 'Modifier',
        'supprimer' => 'Supprimer',
        'valider' => 'Valider',
        'recouvrement' => 'Recouvrement',
        'encaissement' => 'Encaissement',
        'exporter' => 'Exporter',
        'admin' => 'Administration',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libAction) {
            Permission::updateOrCreate(
                ['name' => "recette.$action", 'guard_name' => 'web'],
                ['module' => 'recette', 'action' => $action, 'libelle' => "Recettes — $libAction"]
            );
        }

        // Consultation : tous les rôles qui disposent déjà d'une vision transverse.
        $lecteurs = [
            'president', 'vice_president', 'secretaire_general', 'commissaire',
            'directeur', 'chef_service', 'gestionnaire_budgetaire',
            'controleur_financier', 'comptable', 'auditeur', 'lecteur',
        ];
        foreach ($lecteurs as $name) {
            Role::findByName($name, 'web')?->givePermissionTo('recette.voir');
        }

        // Gestion opérationnelle des recettes (prévisions, recouvrement, encaissement, export).
        Role::findByName('gestionnaire_budgetaire', 'web')?->givePermissionTo([
            'recette.creer', 'recette.modifier', 'recette.recouvrement',
            'recette.encaissement', 'recette.exporter',
        ]);

        // Comptable : encaissement et export (comptabilisation des recettes).
        Role::findByName('comptable', 'web')?->givePermissionTo([
            'recette.encaissement', 'recette.exporter',
        ]);

        // Contrôleur financier : validation (séparation saisie / validation).
        Role::findByName('controleur_financier', 'web')?->givePermissionTo([
            'recette.valider',
        ]);

        // Administration fonctionnelle du module.
        Role::findByName('administrateur', 'web')?->givePermissionTo(
            array_map(fn ($a) => "recette.$a", array_keys($this->actions))
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
