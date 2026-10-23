<?php

namespace Database\Seeders;

use App\Models\Profil;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/** Profils fonctionnels par défaut, reliés aux rôles. */
class ProfilSeeder extends Seeder
{
    public function run(): void
    {
        // [code, libellé, type, rôles]
        $profils = [
            ['PROF-INST', 'Direction institutionnelle', 'institutionnel', ['secretaire_general']],
            ['PROF-METIER', 'Agent métier', 'metier', ['chef_service']],
            ['PROF-BUDGET', 'Gestion budgétaire', 'budgetaire', ['gestionnaire_budgetaire']],
            ['PROF-PAP', 'Planification PAP / GAR', 'pap_gar', ['directeur', 'chef_service']],
            ['PROF-VALID', 'Validation financière', 'validation', ['controleur_financier']],
            ['PROF-CONSULT', 'Consultation', 'consultation', ['lecteur']],
            ['PROF-AUDIT', 'Audit & contrôle', 'audit', ['auditeur']],
        ];

        foreach ($profils as [$code, $libelle, $type, $roles]) {
            $profil = Profil::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'type' => $type, 'actif' => true]);
            $profil->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));
        }
    }
}
