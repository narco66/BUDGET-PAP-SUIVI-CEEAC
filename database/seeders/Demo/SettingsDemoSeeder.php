<?php

namespace Database\Seeders\Demo;

use App\Models\Parametre;
use Illuminate\Database\Seeder;

/** Paramètres système de démonstration (non destructif). */
class SettingsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $parametres = [
            ['institution.nom', 'Commission de la CEEAC', 'Nom de l’institution', 'general', 'string'],
            ['institution.sigle', 'CEEAC', 'Sigle', 'general', 'string'],
            ['budget.devise', 'XAF', 'Devise budgétaire', 'budget', 'string'],
            ['budget.taux_change_usd', '600', 'Taux de change XAF/USD', 'budget', 'number'],
            ['budget.exercice_courant', '2026', 'Exercice budgétaire courant', 'budget', 'number'],
            ['budget.seuil_alerte', '90', "Seuil d'alerte de consommation (%)", 'budget', 'number'],
            ['securite.mfa_obligatoire', 'false', 'MFA obligatoire', 'securite', 'bool'],
            ['securite.expiration_mdp_jours', '90', 'Expiration des mots de passe (jours)', 'securite', 'number'],
            ['workflow.relance_jours', '5', 'Délai de relance des validations (jours)', 'workflow', 'number'],
        ];

        foreach ($parametres as [$cle, $valeur, $libelle, $groupe, $type]) {
            Parametre::firstOrCreate(['cle' => $cle], compact('valeur', 'libelle', 'groupe', 'type'));
        }
    }
}
