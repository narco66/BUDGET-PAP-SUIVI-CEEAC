<?php



namespace Database\Seeders;



use App\Models\ApiDocumentation;

use Illuminate\Database\Seeder;



/** Enregistre la documentation OpenAPI v1 (I-P3). */

class ApiDocumentationSeeder extends Seeder

{

    public function run(): void

    {

        ApiDocumentation::updateOrCreate(

            ['version' => '1.0.0', 'format' => 'openapi'],

            [

                'titre' => 'API REST BUDGET-PAP-SUIVI-CEEAC v1',

                'chemin_fichier' => 'openapi/v1.json',

                'actif' => true,

                'publie_le' => now(),

            ]

        );

    }

}

