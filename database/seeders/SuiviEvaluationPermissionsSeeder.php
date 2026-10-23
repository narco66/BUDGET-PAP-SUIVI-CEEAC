<?php



namespace Database\Seeders;



use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Permission;

use Spatie\Permission\Models\Role;

use Spatie\Permission\PermissionRegistrar;



/** Phase P0 — permissions module Suivi-Évaluation. */

class SuiviEvaluationPermissionsSeeder extends Seeder

{

    /** @var array<string, string> */

    private array $actions = [

        'view' => 'Consulter le suivi-évaluation',

        'create' => 'Saisir / lancer une campagne ou collecte S&E',

        'update' => 'Modifier les données de suivi-évaluation',

        'validate' => 'Valider une évaluation périodique',

        'recommend' => 'Enregistrer des recommandations S&E',

        'export' => 'Exporter rapports et synthèses S&E',

        'admin' => 'Administrer le module suivi-évaluation',

    ];



    public function run(): void

    {

        app(PermissionRegistrar::class)->forgetCachedPermissions();



        foreach ($this->actions as $action => $libelle) {

            Permission::updateOrCreate(

                ['name' => "suivi_evaluation.$action", 'guard_name' => 'web'],

                ['module' => 'suivi_evaluation', 'action' => $action, 'libelle' => $libelle]

            );

        }



        Role::findByName('commissaire', 'web')?->givePermissionTo([

            'suivi_evaluation.view', 'suivi_evaluation.validate', 'suivi_evaluation.export',

        ]);

        Role::findByName('directeur', 'web')?->givePermissionTo([

            'suivi_evaluation.view', 'suivi_evaluation.export', 'suivi_evaluation.recommend',

        ]);

        Role::findByName('chef_service', 'web')?->givePermissionTo([

            'suivi_evaluation.view', 'suivi_evaluation.create', 'suivi_evaluation.update', 'suivi_evaluation.recommend',

        ]);

        Role::findByName('auditeur', 'web')?->givePermissionTo([

            'suivi_evaluation.view', 'suivi_evaluation.export', 'suivi_evaluation.recommend',

        ]);

        Role::findByName('administrateur', 'web')?->givePermissionTo([

            'suivi_evaluation.view', 'suivi_evaluation.create', 'suivi_evaluation.update',

            'suivi_evaluation.validate', 'suivi_evaluation.recommend', 'suivi_evaluation.export', 'suivi_evaluation.admin',

        ]);



        app(PermissionRegistrar::class)->forgetCachedPermissions();

    }

}

