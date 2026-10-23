<?php



namespace Database\Seeders;



use App\Models\ConnectedApp;

use App\Services\Interop\ConnectedAppService;

use Illuminate\Database\Seeder;



/** Phase I-P0/I-P1 — socle interopérabilité : permissions et application démo TOMPRO. */

class InteroperabiliteApiSeeder extends Seeder

{

    public function run(): void

    {

        $this->call(InteroperabiliteApiPermissionsSeeder::class);
        $this->call(WebhookEventsSeeder::class);
        $this->call(ApiDocumentationSeeder::class);



        $app = ConnectedApp::updateOrCreate(

            ['code' => 'TOMPRO'],

            [

                'libelle' => 'Connecteur comptable TOMPRO',

                'description' => 'Échange des paiements et encaissements validés avec écritures SYSCOHADA (D12 — P2-8).',

                'type' => 'interne',

                'contact_email' => 'dsi@ceeac.int',

                'scopes_autorises' => ['tompro'],

                'statut' => 'actif',

            ]

        );



        if ($app->clients()->where('statut', 'actif')->doesntExist()) {

            app(ConnectedAppService::class)->creerClient($app, 'Sandbox');

        }

    }

}

