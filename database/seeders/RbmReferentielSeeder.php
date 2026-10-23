<?php

namespace Database\Seeders;

use App\Models\SourceVerification;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use Illuminate\Database\Seeder;

/** Catalogues référentiels RBM/GAR — seeder non destructif (updateOrCreate). */
class RbmReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'STR', 'libelle' => 'Stratégique', 'description' => 'Indicateur de résultat stratégique', 'ordre' => 1],
            ['code' => 'OPE', 'libelle' => 'Opérationnel', 'description' => 'Indicateur de performance opérationnelle', 'ordre' => 2],
            ['code' => 'FIN', 'libelle' => 'Financier', 'description' => 'Indicateur de suivi financier', 'ordre' => 3],
            ['code' => 'IMP', 'libelle' => 'Impact', 'description' => 'Indicateur d\'impact', 'ordre' => 4],
        ];

        foreach ($types as $t) {
            TypeIndicateur::updateOrCreate(['code' => $t['code']], $t + ['statut' => 'actif']);
        }

        $unites = [
            ['code' => 'PCT', 'libelle' => 'Pourcentage', 'symbole' => '%', 'ordre' => 1],
            ['code' => 'NB', 'libelle' => 'Nombre', 'symbole' => 'u.', 'ordre' => 2],
            ['code' => 'XAF', 'libelle' => 'Franc CFA', 'symbole' => 'XAF', 'ordre' => 3],
            ['code' => 'JOUR', 'libelle' => 'Jours', 'symbole' => 'j', 'ordre' => 4],
        ];

        foreach ($unites as $u) {
            UniteMesure::updateOrCreate(['code' => $u['code']], $u + ['statut' => 'actif']);
        }

        $sources = [
            ['code' => 'RAP-AN', 'libelle' => 'Rapport annuel', 'description' => 'Rapport annuel d\'activité'],
            ['code' => 'ETAT-FIN', 'libelle' => 'États financiers', 'description' => 'États financiers certifiés'],
            ['code' => 'ENQ', 'libelle' => 'Enquête / sondage', 'description' => 'Enquête ou sondage de satisfaction'],
            ['code' => 'REG-BUD', 'libelle' => 'Registre budgétaire', 'description' => 'Registre des engagements et paiements'],
        ];

        foreach ($sources as $s) {
            SourceVerification::updateOrCreate(['code' => $s['code']], $s + ['statut' => 'actif']);
        }
    }
}
