<?php

namespace Database\Seeders;

use App\Models\RapportCategorie;
use App\Models\RapportModele;
use App\Services\Reporting\RapportComposer;
use Illuminate\Database\Seeder;

/** Phase P2 — catalogue institutionnel des modèles de rapports. */
class ReportingCatalogueSeeder extends Seeder
{
    /** @var array<string, array{code:string, libelle:string, ordre:int, types:array<string, string>}> */
    private array $categories = [
        'operationnel' => [
            'code' => 'operationnel',
            'libelle' => 'Rapports opérationnels',
            'ordre' => 10,
            'types' => [
                'pap', 'execution_synthese', 'execution_axe', 'bailleur', 'categorie',
                'rbm_gar', 'marches', 'contrats', 'nomenclature', 'budget_institutionnel',
                'situation_budgetaire', 'indicateurs_evolution', 'ecarts_physique_financier',
                'recommandations', 'formations',
            ],
        ],
        'strategique' => [
            'code' => 'strategique',
            'libelle' => 'Performance stratégique',
            'ordre' => 20,
            'types' => ['performance_pilier', 'performance_axe', 'performance_departement'],
        ],
        'periodique' => [
            'code' => 'periodique',
            'libelle' => 'Rapports périodiques institutionnels',
            'ordre' => 30,
            'types' => ['trimestriel', 'semestriel', 'annuel'],
        ],
        'pilotage' => [
            'code' => 'pilotage',
            'libelle' => 'Pilotage & suivi-évaluation',
            'ordre' => 40,
            'types' => ['suivi_evaluation'],
        ],
        'recettes' => [
            'code' => 'recettes',
            'libelle' => 'Recettes & mobilisation',
            'ordre' => 50,
            'types' => ['recettes_mobilisation', 'recettes_financier'],
        ],
        'ipsas' => [
            'code' => 'ipsas',
            'libelle' => 'Conformité IPSAS',
            'ordre' => 60,
            'types' => ['ipsas_etat_financier'],
        ],
        'technique' => [
            'code' => 'technique',
            'libelle' => 'Rapports techniques',
            'ordre' => 70,
            'types' => ['technique_sante', 'technique_integrite', 'technique_audit'],
        ],
    ];

    public function run(): void
    {
        foreach ($this->categories as $def) {
            $categorie = RapportCategorie::updateOrCreate(
                ['code' => $def['code']],
                ['libelle' => $def['libelle'], 'ordre' => $def['ordre'], 'actif' => true]
            );

            foreach ($def['types'] as $type) {
                if (! isset(RapportComposer::TYPES[$type])) {
                    continue;
                }

                RapportModele::updateOrCreate(
                    ['type' => $type],
                    [
                        'categorie_id' => $categorie->id,
                        'titre' => RapportComposer::TYPES[$type],
                        'description' => 'Modèle institutionnel — '.RapportComposer::TYPES[$type],
                        'parametres_schema' => ['periode' => true, 'exercice' => true],
                        'actif' => true,
                    ]
                );
            }
        }
    }
}
