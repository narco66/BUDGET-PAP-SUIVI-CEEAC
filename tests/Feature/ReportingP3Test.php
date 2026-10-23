<?php

namespace Tests\Feature;

use App\Models\AuditReporting;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\RapportIpsas;
use App\Models\RapportModele;
use App\Models\User;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\RapportIpsasService;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase P3 Reporting — IPSAS, scheduler, audit, API REST. */
class ReportingP3Test extends TestCase
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

    public function test_ipsas_compose_avec_annexes(): void
    {
        $composed = app(RapportComposer::class)->composer('ipsas_etat_financier', $this->exercice);

        $this->assertSame('ipsas_etat_financier', $composed['type']);
        $titres = collect($composed['sections'])->pluck('titre');
        $this->assertTrue($titres->contains('État 1 — Bilan (Actif)'));
        $this->assertTrue($titres->contains('État 2 — Bilan (Passif et fonds propres)'));
        $this->assertTrue($titres->contains('État 3 — Compte de résultat'));
        $this->assertTrue($titres->contains('Annexe 1 — Exécution par nature budgétaire (SYSCOHADA / Titres)'));
        $this->assertTrue($titres->contains('Annexe 3 — Mobilisation des contributions (États membres)'));
    }

    public function test_ipsas_snapshot_enregistre_a_la_generation(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => false, 'reporting.async.enabled' => false]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'ipsas_etat_financier',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]))->assertOk();

        $rapport = Rapport::where('type', 'ipsas_etat_financier')->first();
        $this->assertNotNull($rapport);
        $this->assertDatabaseHas('rapports_ipsas', ['rapport_id' => $rapport->id]);
        $snapshot = RapportIpsas::where('rapport_id', $rapport->id)->first();
        $this->assertSame(2026, $snapshot->donnees_preparatoires['exercice']);
        $this->assertSame('IPSAS-COMPLET', $snapshot->reference_note);
        $this->assertArrayHasKey('budget', $snapshot->donnees_preparatoires);
        $this->assertArrayHasKey('bilan', $snapshot->donnees_preparatoires);
        $this->assertArrayHasKey('compte_resultat', $snapshot->donnees_preparatoires);
    }

    public function test_audit_journalise_telechargement(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => false]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'pap',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]));

        $rapport = Rapport::first();
        Storage::disk('local')->put($rapport->chemin, 'pdf-test');

        $this->actingAs($this->admin)->get(route('reporting.telecharger', $rapport))->assertOk();

        $this->assertDatabaseHas('audit_reporting', [
            'rapport_id' => $rapport->id,
            'action' => 'download',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_scheduler_enregistre_commandes_reporting(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->map(fn ($e) => $e->command ?? '')
            ->filter();

        $this->assertTrue($events->contains(fn ($c) => str_contains($c, 'reporting:generer-trimestre')));
        $this->assertTrue($events->contains(fn ($c) => str_contains($c, 'reporting:generer-semestre')));
        $this->assertTrue($events->contains(fn ($c) => str_contains($c, 'reporting:generer-annuel')));
    }

    public function test_commande_generer_ipsas(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => false]);

        $code = Artisan::call('reporting:generer-ipsas', [
            '--exercice' => $this->exercice->annee,
            '--format' => 'pdf',
        ]);

        $this->assertSame(0, $code);
        $this->assertSame(1, Rapport::where('type', 'ipsas_etat_financier')->count());
    }

    public function test_api_catalogue_exige_authentification(): void
    {
        $this->getJson(route('api.reporting.catalogue'))->assertUnauthorized();
    }

    public function test_api_catalogue_accessible_avec_permission(): void
    {
        Sanctum::actingAs($this->admin, ['lecture']);

        $this->getJson(route('api.reporting.catalogue'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['categories']]);

        $this->assertDatabaseHas('audit_reporting', ['action' => 'api_catalogue', 'user_id' => $this->admin->id]);
    }

    public function test_api_rapport_detail_avec_ipsas(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => false, 'reporting.async.enabled' => false]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'ipsas_etat_financier',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]));

        $rapport = Rapport::where('type', 'ipsas_etat_financier')->first();
        Sanctum::actingAs($this->admin, ['lecture']);

        $this->getJson(route('api.reporting.rapport', $rapport))
            ->assertOk()
            ->assertJsonPath('data.type', 'ipsas_etat_financier')
            ->assertJsonStructure(['data' => ['ipsas' => ['donnees_preparatoires']]]);
    }

    public function test_page_planification_accessible(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reporting.planification'))
            ->assertOk()
            ->assertSee('Planification automatique');
    }

    public function test_catalogue_contient_modele_ipsas(): void
    {
        $this->assertNotNull(RapportModele::where('type', 'ipsas_etat_financier')->first());
    }

    public function test_donnees_preparatoires_coherentes_avec_reporting(): void
    {
        $ipsas = app(RapportIpsasService::class)->donneesPreparatoires($this->exercice);
        $composed = app(RapportComposer::class)->composer('situation_budgetaire', $this->exercice);

        $tauxHub = collect($composed['sections'][0]['items'] ?? [])
            ->firstWhere('label', "Taux d'exécution")['valeur'] ?? null;

        $this->assertEqualsWithDelta(
            $ipsas['budget']['taux_execution'],
            $tauxHub,
            0.01
        );
    }
}
