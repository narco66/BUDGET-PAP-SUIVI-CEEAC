<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use App\Models\User;
use App\Services\Reporting\ActivitesRetardService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cockpit — détection des activités en retard, filtres, exports, fiche détaillée. */
class CockpitActivitesRetardTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private SousProduit $sousProduit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P1', 'intitule' => 'Pilier 1', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe 1', 'ordre' => 1]);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit 1', 'ordre' => 1]);
        $this->sousProduit = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'Sous-produit 1', 'ordre' => 1]);
    }

    private function user(string $role = 'lecteur'): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function activite(string $code, float $tauxPhys, ?string $finTache, string $statut = 'actif'): Activite
    {
        $a = Activite::create([
            'sous_produit_id' => $this->sousProduit->id,
            'code' => $code, 'intitule' => 'Activité '.$code,
            'montant_total' => 5_000_000, 'taux_realisation_physique' => $tauxPhys,
            'taux_realisation_financier' => 10, 'statut' => $statut, 'ordre' => 1,
        ]);
        if ($finTache) {
            Tache::create([
                'activite_id' => $a->id, 'code' => $code.'-T1', 'intitule' => 'Tâche',
                'date_fin' => $finTache, 'taux_realisation' => 0, 'ordre' => 1,
            ]);
        }

        return $a;
    }

    public function test_detecte_uniquement_les_activites_en_retard(): void
    {
        $this->activite('ACT-RETARD', 40, '2026-01-31');          // échéance passée, <100 % → en retard
        $this->activite('ACT-FUTUR', 40, '2026-12-31');           // échéance future → non
        $this->activite('ACT-100', 100, '2026-01-31');            // 100 % → non
        $this->activite('ACT-CLOS', 40, '2026-01-31', 'cloturee'); // clôturée → non
        $this->activite('ACT-SANS-DATE', 40, null);                // pas d'échéance → non

        $retards = app(ActivitesRetardService::class)->collection($this->exercice->id);

        $this->assertCount(1, $retards);
        $this->assertSame('ACT-RETARD', $retards->first()->code);
    }

    public function test_synthese_et_jours_de_retard(): void
    {
        $this->activite('ACT-1', 30, '2026-01-31');
        $svc = app(ActivitesRetardService::class);

        $synthese = $svc->synthese($this->exercice->id);
        $this->assertSame(1, $synthese['total']);
        $this->assertEquals(5_000_000.0, $synthese['montant']);

        $a = $svc->collection($this->exercice->id)->first();
        $this->assertGreaterThan(30, $svc->joursRetard($a)); // du 31/01 au 12/06 > 30 j
        $this->assertSame('danger', $svc->niveau($svc->joursRetard($a)));
    }

    public function test_niveaux_de_couleur(): void
    {
        $svc = app(ActivitesRetardService::class);
        $this->assertSame('warning', $svc->niveau(10));
        $this->assertSame('orange', $svc->niveau(20));
        $this->assertSame('danger', $svc->niveau(45));
    }

    public function test_filtre_par_pilier(): void
    {
        $this->activite('ACT-1', 40, '2026-01-31');
        $autrePilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P2', 'intitule' => 'P2', 'ordre' => 2]);

        $svc = app(ActivitesRetardService::class);
        $pilier1 = Pilier::where('code', 'P1')->first();

        $this->assertCount(1, $svc->collection($this->exercice->id, ['pilier_id' => $pilier1->id]));
        $this->assertCount(0, $svc->collection($this->exercice->id, ['pilier_id' => $autrePilier->id]));
    }

    public function test_cockpit_affiche_la_section(): void
    {
        $this->activite('ACT-1', 40, '2026-01-31');

        $this->actingAs($this->user())
            ->get(route('reporting.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Activités en retard', false)
            ->assertSee('ACT-1');
    }

    public function test_cockpit_affiche_la_bande_suivi_evaluation(): void
    {
        $this->activite('ACT-1', 40, '2026-01-31');

        $this->actingAs($this->user())
            ->get(route('reporting.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Performance & Suivi-Évaluation', false) // bande S&E ajoutée au cockpit exécutif
            ->assertSee('Réalisation physique PAP', false)
            ->assertSee('Feux indicateurs', false);
    }

    public function test_fiche_detaillee_accessible(): void
    {
        $a = $this->activite('ACT-1', 40, '2026-01-31');

        $this->actingAs($this->user())
            ->get(route('reporting.cockpit.activite', $a))
            ->assertOk()
            ->assertSee('Référentiel RBM', false)
            ->assertSee('Informations budgétaires', false);
    }

    public function test_exports_pdf_excel_csv(): void
    {
        $this->activite('ACT-1', 40, '2026-01-31');
        $user = $this->user();

        $this->actingAs($user)->get(route('reporting.cockpit.retard.export', ['format' => 'pdf', 'exercice' => $this->exercice->id]))->assertOk();
        $this->actingAs($user)->get(route('reporting.cockpit.retard.export', ['format' => 'xlsx', 'exercice' => $this->exercice->id]))->assertOk();
        $this->actingAs($user)->get(route('reporting.cockpit.retard.export', ['format' => 'csv', 'exercice' => $this->exercice->id]))->assertOk();
    }

    public function test_gating_acces_refuse_sans_permission(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->get(route('reporting.cockpit', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }
}
