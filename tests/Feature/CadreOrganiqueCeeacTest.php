<?php

namespace Tests\Feature;

use App\Models\Structure;
use App\Services\Institution\CadreOrganiqueCeeacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadreOrganiqueCeeacTest extends TestCase
{
    use RefreshDatabase;

    public function test_synchronisation_cadre_organique_cree_la_structure_officielle(): void
    {
        app(CadreOrganiqueCeeacService::class)->synchroniser();

        $this->assertDatabaseHas('structures', ['code' => 'DSG', 'type' => 'departement']);
        $this->assertDatabaseHas('structures', ['code' => 'BCJ', 'libelle' => 'Bureau du Conseiller Juridique']);
        $this->assertDatabaseHas('structures', ['code' => 'DIR-PPB', 'type' => 'direction']);
        $this->assertDatabaseHas('structures', ['code' => 'SVC-BUDGET', 'type' => 'service']);

        $service = Structure::where('code', 'SVC-BUDGET')->firstOrFail();
        $this->assertSame('DIR-PPB', $service->direction()?->code);
        $this->assertSame('DSG', $service->departement()?->code);

        $this->assertDatabaseHas('structures', ['code' => 'DIR-APPS-GOUV', 'type' => 'direction']);
        $this->assertSame(
            'DAPPS',
            Structure::where('code', 'SVC-APPS-PAIX')->firstOrFail()->departement()?->code
        );
    }

    public function test_synchronisation_migre_les_libelles_obsoletes_sans_changer_id(): void
    {
        $ancienne = Structure::create([
            'code' => 'DAJ',
            'libelle' => 'Direction des Affaires Juridiques',
            'type' => 'direction',
            'niveau' => 1,
        ]);

        app(CadreOrganiqueCeeacService::class)->synchroniser();

        $this->assertDatabaseHas('structures', [
            'code' => 'BCJ',
            'libelle' => 'Bureau du Conseiller Juridique',
        ]);
        $this->assertDatabaseHas('structures', [
            'id' => $ancienne->id,
            'libelle' => 'Bureau du Conseiller Juridique',
        ]);
    }

    public function test_commande_audit_cadre_reussit_apres_correction(): void
    {
        $this->artisan('institution:audit-cadre --fix')->assertSuccessful();
    }
}
