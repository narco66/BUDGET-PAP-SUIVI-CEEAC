<?php

namespace Tests\Feature;

use App\Models\Contrat;
use App\Models\Exercice;
use App\Models\Marche;
use App\Models\Rapport;
use App\Models\User;
use App\Services\Reporting\ReportingService;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarchesContratsTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(SafeDatabaseSeeder::class);
        $this->exercice = Exercice::firstWhere('annee', 2026);
        $this->admin = User::firstWhere('email', 'administrateur@ceeac.test');
    }

    public function test_demo_relie_marches_et_contrats_aux_engagements(): void
    {
        // Au moins un marché et un contrat rattachés à un engagement de type marché
        $this->assertGreaterThan(0, Marche::whereNotNull('engagement_id')->count());
        $this->assertGreaterThan(0, Contrat::whereNotNull('engagement_id')->count());
        // Marchés planifiés (PPM) présents
        $this->assertTrue(Marche::where('numero', 'MAR-2026-P001')->exists());
    }

    public function test_crud_marche_et_contrat(): void
    {
        // Index accessibles
        $this->actingAs($this->admin)->get(route('marches.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('contrats.index'))->assertOk();

        // Création d'un marché (numéro auto)
        $this->actingAs($this->admin)->post(route('marches.store'), [
            'exercice_id' => $this->exercice->id, 'objet' => 'Marché test',
            'type_marche' => 'services', 'mode_passation' => 'consultation',
            'montant_estime' => 5_000_000, 'statut' => 'planifie',
        ])->assertRedirect(route('marches.index'));
        $this->assertDatabaseHas('marches', ['objet' => 'Marché test', 'montant_estime' => 5_000_000]);

        $marche = Marche::firstWhere('objet', 'Marché test');
        $this->actingAs($this->admin)->get(route('marches.show', $marche))->assertOk();

        // Création d'un contrat lié
        $this->actingAs($this->admin)->post(route('contrats.store'), [
            'exercice_id' => $this->exercice->id, 'marche_id' => $marche->id,
            'objet' => 'Contrat test', 'titulaire' => 'Société X',
            'type_contrat' => 'marche', 'montant' => 4_800_000, 'statut' => 'signe',
        ])->assertRedirect(route('contrats.index'));
        $this->assertDatabaseHas('contrats', ['objet' => 'Contrat test', 'titulaire' => 'Société X']);
    }

    public function test_rapports_marches_et_contrats(): void
    {
        Storage::fake('local');
        config(['reporting.async.enabled' => false]);

        foreach (['marches', 'contrats'] as $type) {
            foreach (['pdf', 'excel', 'word'] as $format) {
                $this->actingAs($this->admin)->get(route('reporting.generer', [
                    'type' => $type, 'format' => $format, 'exercice' => $this->exercice->id,
                ]))->assertOk();
            }
        }
        $this->assertSame(6, Rapport::whereIn('type', ['marches', 'contrats'])->count());
    }

    public function test_registre_contrats_coherent(): void
    {
        $registre = app(ReportingService::class)->contratsRegistre($this->exercice->id);

        // Pour chaque contrat lié à un engagement visé : payé ≤ engagé (cohérence chaîne)
        foreach ($registre as $r) {
            if ($r['engage'] > 0) {
                $this->assertLessThanOrEqual($r['engage'] + 0.01, $r['paye'], "Payé > engagé pour {$r['contrat']}");
            }
        }
        $this->assertNotEmpty($registre);
    }

    public function test_permission_creation_refusee_sans_droit(): void
    {
        $lecteur = User::firstWhere('email', 'lecteur@ceeac.test');
        // Le lecteur voit mais ne peut pas créer
        $this->actingAs($lecteur)->get(route('marches.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('marches.create'))->assertForbidden();
    }

    public function test_api_session_marches_et_contrats(): void
    {
        $liste = $this->actingAs($this->admin)
            ->getJson(route('api.marches.index', ['exercice' => $this->exercice->id]))
            ->assertOk();
        $this->assertIsString($liste->json('data.0.montant_estime'));

        $creation = $this->actingAs($this->admin)->postJson(route('api.marches.store'), [
            'exercice_id' => $this->exercice->id,
            'objet' => 'Marché API SPA',
            'type_marche' => 'services',
            'mode_passation' => 'consultation',
            'montant_estime' => '5000000.00',
            'statut' => 'planifie',
        ])->assertCreated();
        $this->assertSame('5000000.00', $creation->json('marche.montant_estime'));
        $marcheId = $creation->json('marche.id');

        $this->actingAs($this->admin)
            ->getJson(route('api.marches.show', $marcheId))
            ->assertOk()
            ->assertJsonPath('data.montant_estime', '5000000.00');

        $contrat = $this->actingAs($this->admin)->postJson(route('api.contrats.store'), [
            'exercice_id' => $this->exercice->id,
            'marche_id' => $marcheId,
            'objet' => 'Contrat API SPA',
            'titulaire' => 'Société Y',
            'type_contrat' => 'marche',
            'montant' => '4800000.00',
            'statut' => 'signe',
        ])->assertCreated();
        $this->assertSame('4800000.00', $contrat->json('contrat.montant'));

        $lecteur = User::firstWhere('email', 'lecteur@ceeac.test');
        $this->actingAs($lecteur)
            ->getJson(route('api.marches.index'))
            ->assertOk();
        $this->actingAs($lecteur)
            ->postJson(route('api.marches.store'), [
                'exercice_id' => $this->exercice->id,
                'objet' => 'Interdit',
                'type_marche' => 'services',
                'mode_passation' => 'consultation',
                'montant_estime' => '1.00',
                'statut' => 'planifie',
            ])
            ->assertForbidden();
        $this->actingAs($lecteur)
            ->postJson(route('api.contrats.store'), [
                'exercice_id' => $this->exercice->id,
                'objet' => 'Interdit',
                'titulaire' => 'X',
                'type_contrat' => 'marche',
                'montant' => '1.00',
                'statut' => 'projet',
            ])
            ->assertForbidden();
    }
}
