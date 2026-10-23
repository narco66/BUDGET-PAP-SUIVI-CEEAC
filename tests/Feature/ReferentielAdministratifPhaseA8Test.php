<?php

namespace Tests\Feature;

use App\Models\Poste;
use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\InstitutionImportService;
use Database\Seeders\PosteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase A8 — CRUD postes, import structures, permissions administratif.* */
class ReferentielAdministratifPhaseA8Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PosteSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('administrateur');
    }

    public function test_crud_poste(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('postes.store'), [
                'code' => 'CP-A8',
                'libelle' => 'Chef de projet A8',
                'actif' => 1,
            ])
            ->assertRedirect(route('postes.index'));

        $poste = Poste::where('code', 'CP-A8')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('postes.update', $poste), [
                'code' => 'CP-A8',
                'libelle' => 'Chef de projet mis a jour',
                'actif' => 1,
            ])
            ->assertRedirect(route('postes.index'));

        $this->assertSame('Chef de projet mis a jour', $poste->fresh()->libelle);

        $this->actingAs($admin)->get(route('postes.index'))->assertOk()->assertSee('CP-A8');
    }

    public function test_poste_refuse_sans_permission(): void
    {
        $user = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('point_focal');

        $this->actingAs($user)->get(route('postes.index'))->assertForbidden();
    }

    public function test_permission_administratif_voir_acces_structures(): void
    {
        $user = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $user->givePermissionTo(Permission::findByName('administratif.voir', 'web'));

        $this->actingAs($user)->get(route('structures.index'))->assertOk();
        $this->actingAs($user)->get(route('postes.index'))->assertOk();
    }

    public function test_permission_administratif_miroir_directeur(): void
    {
        $directeur = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('directeur');

        $this->assertTrue($directeur->can('administratif.voir'));
        $this->assertTrue($directeur->can('administratif.exporter'));
    }

    public function test_import_structure_cree_et_met_a_jour(): void
    {
        Structure::create([
            'code' => 'DEP-BASE',
            'libelle' => 'Departement base',
            'type' => 'departement',
            'actif' => true,
        ]);

        $stats = app(InstitutionImportService::class)->importerStructures(new Collection([
            [
                'code' => 'DIR-A8',
                'libelle' => 'Direction importee',
                'type' => 'direction',
                'parent_code' => 'DEP-BASE',
                'actif' => 'oui',
            ],
            [
                'code' => 'DIR-A8',
                'libelle' => 'Direction importee MAJ',
                'type' => 'direction',
                'parent_code' => 'DEP-BASE',
                'actif' => 'oui',
            ],
        ]));

        $this->assertSame(1, $stats['crees']);
        $this->assertSame(1, $stats['mis_a_jour']);
        $this->assertDatabaseHas('structures', [
            'code' => 'DIR-A8',
            'libelle' => 'Direction importee MAJ',
        ]);
    }

    public function test_import_signale_hierarchie_invalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(InstitutionImportService::class)->importerStructures(new Collection([
            [
                'code' => 'SVC-INV',
                'libelle' => 'Service sans direction',
                'type' => 'service',
                'parent_code' => '',
                'actif' => 'oui',
            ],
        ]));
    }

    public function test_route_import_modele_excel(): void
    {
        Excel::fake();

        $this->actingAs($this->admin())
            ->get(route('structure.importer.modele'))
            ->assertOk();

        Excel::assertDownloaded('modele-import-structures.xlsx');
    }

    public function test_route_import_refuse_sans_permission(): void
    {
        $user = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('point_focal');

        $this->actingAs($user)->get(route('structure.importer.modele'))->assertForbidden();
    }

    public function test_ecran_structures_affiche_import(): void
    {
        $this->actingAs($this->admin())
            ->get(route('structures.index'))
            ->assertOk()
            ->assertSee('Importer structures');
    }
}
