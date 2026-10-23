<?php



namespace Database\Factories;



use App\Models\Indicateur;

use App\Models\Pilier;

use App\Models\TypeIndicateur;

use App\Models\UniteMesure;

use Illuminate\Database\Eloquent\Factories\Factory;



/** @extends Factory<Indicateur> */

class IndicateurFactory extends Factory

{

    protected $model = Indicateur::class;



    public function definition(): array

    {

        return [

            'rbm_type' => Pilier::class,

            'rbm_id' => Pilier::factory(),

            'code' => 'IND-'.fake()->unique()->numberBetween(1000, 9999),

            'libelle' => fake()->randomElement([
                'Taux de realisation physique des activites planifiees',
                'Taux de production des rapports de performance',
                'Taux d execution des recommandations validees',
                'Taux de couverture des indicateurs suivis',
            ]),

            'type_indicateur_id' => fn () => TypeIndicateur::firstOrCreate(

                ['code' => 'OPE'],

                ['libelle' => 'Opérationnel', 'statut' => 'actif', 'ordre' => 2]

            )->id,

            'unite_mesure_id' => fn () => UniteMesure::firstOrCreate(

                ['code' => 'PCT'],

                ['libelle' => 'Pourcentage', 'symbole' => '%', 'statut' => 'actif', 'ordre' => 1]

            )->id,

            'type' => 'operationnel',

            'unite' => '%',

            'periodicite' => 'trimestriel',

            'valeur_reference' => 0,

            'valeur_cible' => 100,

            'seuil_alerte' => 50,

            'statut' => 'actif',

        ];

    }

}

