<?php

namespace Database\Seeders;

use App\Models\Structure;
use App\Services\Institution\AliasDemonstrationService;
use App\Services\Institution\CadreOrganiqueCeeacService;
use Illuminate\Database\Seeder;

/** Ossature institutionnelle de la Commission de la CEEAC. */
class StructureSeeder extends Seeder
{
    public function run(): void
    {
        app(CadreOrganiqueCeeacService::class)->synchroniser();

        $sg = Structure::where('code', 'SG')->value('id');
        $dirPpb = Structure::where('code', 'DIR-PPB')->value('id');

        Structure::updateOrCreate(
            ['code' => 'DEPT-PLAN'],
            [
                'libelle' => 'Departement de la Planification et du Budget',
                'sigle' => 'DPB',
                'type' => 'departement',
                'parent_id' => $sg,
                'niveau' => 2,
                'ordre' => 900,
                'actif' => true,
            ]
        );

        Structure::updateOrCreate(
            ['code' => 'DIR-BUDGET'],
            [
                'libelle' => 'Direction du Budget',
                'sigle' => 'DB',
                'type' => 'direction',
                'parent_id' => $dirPpb,
                'niveau' => 4,
                'ordre' => 910,
                'actif' => true,
            ]
        );

        $dirBudgetId = Structure::where('code', 'DIR-BUDGET')->value('id');

        Structure::updateOrCreate(
            ['code' => 'SVC-EXEC'],
            [
                'libelle' => "Service de l'Execution Budgetaire",
                'sigle' => 'SEB',
                'type' => 'service',
                'parent_id' => $dirBudgetId,
                'niveau' => 5,
                'ordre' => 910,
                'actif' => true,
            ]
        );

        app(AliasDemonstrationService::class)->rapprocher();
    }
}
