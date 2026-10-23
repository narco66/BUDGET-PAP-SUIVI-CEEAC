<?php

namespace Database\Seeders;

use App\Models\CategorieRisque;
use App\Models\Risque;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module Audit, Contrôle Interne & Conformité — Lot A (Gestion des risques).
 * Idempotent et non destructif : permissions `risques.*` + 8 catégories de risques normées
 * (ISO 31000 / COSO). Réutilisable via `php artisan db:seed --class=AuditControleConformiteSeeder`.
 */
class AuditControleConformiteSeeder extends Seeder
{
    /** @var array<string,string> */
    private array $actions = [
        'voir' => 'Consulter les risques',
        'creer' => 'Enregistrer un risque',
        'modifier' => 'Modifier un risque',
        'supprimer' => 'Archiver un risque',
        'valider' => 'Évaluer / valider un risque',
        'exporter' => 'Exporter le registre des risques',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->actions as $action => $libelle) {
            Permission::updateOrCreate(
                ['name' => "risques.$action", 'guard_name' => 'web'],
                ['module' => 'risques', 'action' => $action, 'libelle' => "Gestion des risques — $libelle"]
            );
        }

        Role::findByName('administrateur', 'web')?->givePermissionTo(array_map(fn ($a) => "risques.$a", array_keys($this->actions)));
        Role::findByName('auditeur', 'web')?->givePermissionTo([
            'risques.voir', 'risques.creer', 'risques.modifier', 'risques.supprimer', 'risques.valider', 'risques.exporter',
            // Lot B — exécution des missions d'audit (le module `audit` est créé par RoleSeeder).
            'audit.creer', 'audit.modifier', 'audit.valider',
            // Lot C — contrôle interne COSO + conformité (modules créés par RoleSeeder).
            'controle_interne.voir', 'controle_interne.creer', 'controle_interne.modifier', 'controle_interne.valider',
            'conformite.voir', 'conformite.creer', 'conformite.modifier', 'conformite.valider',
        ]);
        Role::findByName('directeur', 'web')?->givePermissionTo([
            'risques.voir', 'risques.creer', 'risques.modifier', 'risques.valider',
            'controle_interne.voir', 'conformite.voir', 'conformite.creer', 'conformite.modifier',
        ]);

        // Catégories de risques normées (ISO 31000 / COSO).
        foreach (Risque::CATEGORIES as $code => $libelle) {
            CategorieRisque::updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'ordre' => array_search($code, array_keys(Risque::CATEGORIES), true) + 1]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
