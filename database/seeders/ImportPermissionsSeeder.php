<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions du module Import (non destructif, idempotent). Séparation préparation / approbation / exécution :
 * les gestionnaires préparent et exécutent, la Direction et le SG approuvent (avec le rôle de l'étape du
 * circuit), l'administrateur dispose de tout. Un import exige EN PLUS le droit métier du domaine cible.
 */
class ImportPermissionsSeeder extends Seeder
{
    /** @var array<string, string> */
    private const ACTIONS = [
        'view' => 'Consulter les imports',
        'create' => 'Créer un lot',
        'upload' => 'Déposer un fichier',
        'map' => 'Définir la correspondance des colonnes',
        'validate' => 'Analyser et valider',
        'simulate' => 'Simuler',
        'approve' => 'Approuver un lot sensible',
        'execute' => 'Exécuter un lot',
        'history' => 'Consulter l\'historique de tous les lots',
        'download_report' => 'Télécharger les rapports',
        'retry' => 'Relancer un lot interrompu',
        'cancel' => 'Annuler un lot',
    ];

    /** @var array<string, list<string>> */
    private const ROLES = [
        'gestionnaire_budgetaire' => ['view', 'create', 'upload', 'map', 'validate', 'simulate', 'execute', 'download_report', 'cancel'],
        'directeur' => ['view', 'create', 'upload', 'map', 'validate', 'simulate', 'approve', 'history', 'download_report', 'cancel'],
        'secretaire_general' => ['view', 'approve', 'history', 'download_report'],
        'controleur_financier' => ['view', 'history', 'download_report'],
        'auditeur' => ['view', 'history', 'download_report'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::ACTIONS as $action => $libelle) {
            Permission::query()->updateOrCreate(
                ['name' => "import.{$action}", 'guard_name' => 'web'],
                ['module' => 'import', 'action' => $action, 'libelle' => "Import — {$libelle}"],
            );
        }
        Role::findByName('administrateur', 'web')?->givePermissionTo(array_map(fn ($a) => "import.{$a}", array_keys(self::ACTIONS)));
        foreach (self::ROLES as $role => $actions) {
            Role::query()->where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo(array_map(fn ($a) => "import.{$a}", $actions));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
