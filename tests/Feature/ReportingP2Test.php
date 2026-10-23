<?php

namespace Tests\Feature;

use App\Jobs\GenererRapportJob;
use App\Models\Exercice;
use App\Models\Ged\GedDocument;
use App\Models\Rapport;
use App\Models\RapportModele;
use App\Models\User;
use App\Services\Reporting\RapportComposer;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase P2 Reporting — performance stratégique, catalogue, workflow MOA, GED auto, recettes. */
class ReportingP2Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SafeDatabaseSeeder::class);
        $this->exercice = Exercice::firstWhere('annee', 2026);
        $this->admin = User::firstWhere('email', 'administrateur@ceeac.test');
    }

    public function test_catalogue_modeles_seede(): void
    {
        $this->assertGreaterThanOrEqual(20, RapportModele::count());
        $this->assertNotNull(RapportModele::where('type', 'performance_pilier')->first());
        $this->assertNotNull(RapportModele::where('type', 'recettes_financier')->first());
    }

    public function test_rapport_performance_pilier_compose(): void
    {
        $composed = app(RapportComposer::class)->composer('performance_pilier', $this->exercice);

        $this->assertSame('performance_pilier', $composed['type']);
        $this->assertNotEmpty($composed['sections']);
        $this->assertSame('table', $composed['sections'][2]['type'] ?? null);
    }

    public function test_ecarts_physique_inclut_collectes(): void
    {
        $composed = app(RapportComposer::class)->composer('ecarts_physique_financier', $this->exercice);

        $this->assertSame('Campagnes de suivi physique (S&E)', $composed['sections'][0]['titre'] ?? null);
        $this->assertSame('Campagnes de collecte sur l\'exercice', $composed['sections'][1]['titre'] ?? null);
    }

    public function test_recettes_mobilisation_dans_hub(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'recettes_mobilisation',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]))->assertOk();

        $rapport = Rapport::where('type', 'recettes_mobilisation')->first();
        $this->assertNotNull($rapport);
        $this->assertNotNull($rapport->modele_id);
    }

    public function test_ged_auto_index_apres_generation(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => true]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'execution_synthese',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]))->assertOk();

        $rapport = Rapport::where('type', 'execution_synthese')->latest()->first();
        $this->assertTrue($rapport->ged_indexe);
        $this->assertTrue(GedDocument::where('module_source', 'REPORTING')->where('module_source_id', $rapport->id)->exists());
    }

    public function test_workflow_valider_publier(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => false]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'pap',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]))->assertOk();

        $rapport = Rapport::first();
        $this->assertSame('genere', $rapport->statut_workflow);

        $sg = User::firstWhere('email', 'secretaire_general@ceeac.test');

        $this->actingAs($sg)
            ->post(route('reporting.valider', $rapport))
            ->assertRedirect();

        $rapport->refresh();
        $this->assertSame('valide', $rapport->statut_workflow);
        $this->assertNotNull($rapport->valide_par);

        $this->actingAs($sg)
            ->post(route('reporting.publier', $rapport))
            ->assertRedirect();

        $rapport->refresh();
        $this->assertSame('publie', $rapport->statut_workflow);
        $this->assertTrue($rapport->verrouille);
        $this->assertNotNull($rapport->publie_par);
    }

    public function test_publication_requiert_validation(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => false]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'pap',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]));

        $rapport = Rapport::first();
        $sg = User::firstWhere('email', 'secretaire_general@ceeac.test');

        $this->actingAs($sg)
            ->post(route('reporting.publier', $rapport))
            ->assertForbidden();
    }

    public function test_controleur_peut_valider_mais_pas_publier_sans_droit(): void
    {
        Storage::fake('local');
        config(['reporting.ged_auto_index' => false]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'execution_synthese',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]));

        $rapport = Rapport::first();
        $controleur = User::firstWhere('email', 'controleur_financier@ceeac.test');

        $this->actingAs($controleur)
            ->post(route('reporting.valider', $rapport))
            ->assertRedirect();

        $this->actingAs($controleur)
            ->post(route('reporting.publier', $rapport->fresh()))
            ->assertForbidden();
    }

    public function test_rapport_lourd_dispatch_job_async(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'annuel',
            'format' => 'pdf',
            'exercice' => $this->exercice->id,
        ]))->assertRedirect();

        Queue::assertPushed(GenererRapportJob::class, fn (GenererRapportJob $job) => $job->type === 'annuel'
            && $job->exerciceId === $this->exercice->id
            && $job->userId === $this->admin->id);
    }

    public function test_format_word_dispatch_job_async(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'pap',
            'format' => 'word',
            'exercice' => $this->exercice->id,
        ]))->assertRedirect();

        Queue::assertPushed(GenererRapportJob::class, fn (GenererRapportJob $job) => $job->type === 'pap' && $job->format === 'word');
    }

    public function test_job_async_genere_rapport(): void
    {
        Storage::fake('local');

        $job = new GenererRapportJob('pap', $this->exercice->id, 'pdf', [], $this->admin->id);
        $job->handle(app(\App\Services\Reporting\RapportRenderer::class), app(\App\Services\Notifications\ReportingNotificationService::class));

        $this->assertDatabaseHas('rapports', ['type' => 'pap', 'genere_par' => $this->admin->id]);
    }
}
