<?php

namespace Tests\Feature;

use App\Models\CategorieRisque;
use App\Models\PapRisque;
use App\Models\Risque;
use App\Models\User;
use App\Services\Risque\RisqueService;
use Database\Seeders\AuditControleConformiteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RisqueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AuditControleConformiteSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Risk', 'email' => 'admin.risk@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    private function risque(string $code, int $p, int $i): Risque
    {
        return Risque::create([
            'code' => $code, 'libelle' => 'Risque '.$code,
            'probabilite' => $p, 'impact' => $i, 'statut' => 'identifie',
        ]);
    }

    public function test_seeder_cree_les_categories_et_permissions(): void
    {
        $this->assertSame(8, CategorieRisque::count());
        $this->assertTrue($this->admin->can('risques.creer'));
        $this->assertTrue($this->admin->can('risques.valider'));
    }

    public function test_criticite_et_niveau(): void
    {
        $this->assertSame(16, $this->risque('R-CRIT', 4, 4)->criticite);
        $this->assertSame('critique', Risque::niveauPour(16));
        $this->assertSame('eleve', Risque::niveauPour(9));
        $this->assertSame('modere', Risque::niveauPour(4));
        $this->assertSame('faible', Risque::niveauPour(2));

        $r = $this->risque('R-MOD', 2, 2);
        $this->assertSame(4, $r->criticite);
        $this->assertSame('modere', $r->niveau);
    }

    public function test_registre_crud_via_http(): void
    {
        $cat = CategorieRisque::firstWhere('code', 'FRAU');

        $this->actingAs($this->admin)->post(route('risques.store'), [
            'code' => 'R-2026-001', 'categorie_risque_id' => $cat->id, 'libelle' => 'Détournement de fonds',
            'probabilite' => 3, 'impact' => 4, 'statut' => 'identifie',
        ])->assertRedirect();

        $this->assertDatabaseHas('risques', ['code' => 'R-2026-001', 'probabilite' => 3, 'impact' => 4]);
        $this->actingAs($this->admin)->get(route('risques.index'))->assertOk()->assertSee('R-2026-001');
    }

    public function test_cartographie_compte_les_cellules(): void
    {
        $this->risque('R-A', 4, 4); // critique
        $this->risque('R-B', 4, 4); // critique (même cellule)
        $this->risque('R-C', 1, 1); // faible

        $carto = app(RisqueService::class)->cartographie(false);
        $this->assertSame(2, $carto['cellules'][4][4]['nombre']);
        $this->assertSame(1, $carto['cellules'][1][1]['nombre']);
        $this->assertSame(2, $carto['par_niveau']['critique']);

        $this->actingAs($this->admin)->get(route('risques.cartographie'))
            ->assertOk()->assertSee('Cartographie des risques')->assertSee('Matrice de criticité');
    }

    public function test_agregation_des_risques_existants(): void
    {
        $exercice = \App\Models\Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $pap = \App\Models\Pap::create(['exercice_id' => $exercice->id, 'libelle' => 'PAP 2026', 'statut' => 'brouillon']);
        PapRisque::create([
            'pap_id' => $pap->id, 'code' => 'RP-1', 'libelle' => 'Risque PAP',
            'probabilite' => 'moyenne', 'impact' => 'eleve', 'statut' => 'ouvert',
        ]);

        // moyenne × eleve = 2 × 3 = 6 → modéré ; le risque hérité est normalisé sur l'échelle 1-4.
        $existants = app(RisqueService::class)->existantsNormalises();
        $pap = $existants->firstWhere('code', 'RP-1');
        $this->assertNotNull($pap);
        $this->assertSame(2, $pap->probabilite);
        $this->assertSame(3, $pap->impact);
        $this->assertSame('modere', $pap->niveau);

        // La cartographie consolidée inclut le risque existant.
        $this->assertSame(1, app(RisqueService::class)->cartographie(true)['total']);
    }

    public function test_permissions_registre(): void
    {
        $lecteur = User::create([
            'name' => 'Lecteur Risk', 'email' => 'lecteur.risk@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        $this->actingAs($lecteur)->get(route('risques.index'))->assertOk();          // risques.voir via *.voir
        $this->actingAs($lecteur)->get(route('risques.create'))->assertForbidden();  // risques.creer requis
    }
}
