<?php

namespace Database\Seeders\Demo;

use App\Models\Profil;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Un utilisateur de démonstration par rôle (13), rattaché à une structure (non destructif). */
class UsersDemoSeeder extends Seeder
{
    public function run(): void
    {
        // [rôle, nom, matricule, code structure, code profil]
        $users = [
            ['administrateur', 'Administrateur Démo', 'DEMO-ADM', 'SG', null],
            ['president', 'Président de la Commission', 'DEMO-PRES', 'PRES', 'PROF-INST'],
            ['vice_president', 'Vice-Président', 'DEMO-VPRES', 'VPRES', 'PROF-INST'],
            ['secretaire_general', 'Secrétaire Général', 'DEMO-SG', 'SG', 'PROF-INST'],
            ['commissaire', 'Commissaire Intégration', 'DEMO-COM', 'SG', 'PROF-METIER'],
            ['directeur', 'Directeur du Budget', 'DEMO-DIR', 'DIR-BUDGET', 'PROF-PAP'],
            ['chef_service', "Chef du Service d'Exécution", 'DEMO-CS', 'SVC-EXEC', 'PROF-METIER'],
            ['gestionnaire_budgetaire', 'Gestionnaire Budgétaire', 'DEMO-GB', 'SVC-EXEC', 'PROF-BUDGET'],
            ['controleur_financier', 'Contrôleur Financier', 'DEMO-CF', 'DIR-BUDGET', 'PROF-VALID'],
            ['comptable', 'Agent Comptable', 'DEMO-CPT', 'DIR-BUDGET', 'PROF-BUDGET'],
            ['auditeur', 'Auditeur Interne', 'DEMO-AUD', 'SG', 'PROF-AUDIT'],
            ['point_focal', 'Point Focal Programme', 'DEMO-PF', 'DEPT-PLAN', 'PROF-METIER'],
            ['lecteur', 'Lecteur Consultation', 'DEMO-LEC', 'SG', 'PROF-CONSULT'],
        ];

        foreach ($users as [$role, $nom, $matricule, $structureCode, $profilCode]) {
            $structureId = Structure::where('code', $structureCode)->value('id');

            $user = User::firstOrCreate(
                ['email' => $role.'@ceeac.test'],
                [
                    'name' => $nom,
                    'matricule' => $matricule,
                    'password' => Hash::make('password'),
                    'structure_id' => $structureId,
                    'actif' => true,
                    'statut' => 'actif',
                    'doit_changer_mot_de_passe' => false, // comptes démo prêts à l'emploi
                ]
            );

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
            if ($profilCode && ($profil = Profil::firstWhere('code', $profilCode))) {
                $user->profils()->syncWithoutDetaching([$profil->id]);
            }
            if ($structureId) {
                $user->structures()->syncWithoutDetaching([$structureId => ['principal' => true]]);
            }
        }
    }
}
