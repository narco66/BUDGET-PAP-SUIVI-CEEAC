<?php

namespace Tests\Feature;

use App\Jobs\GenererRapportJob;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\ReportingPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P2 — Industrialisation des performances : génération asynchrone des rapports lourds via file
 * d'attente. Le rendu synchrone existant reste inchangé ; l'option « en arrière-plan » dépose un
 * {@see GenererRapportJob} qui archive le rapport (disponible ensuite au Centre d'exports).
 */
class GenererRapportAsyncTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ReportingPermissionsSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    public function test_le_endpoint_async_depose_le_job_en_file(): void
    {
        Queue::fake();

        $president = User::factory()->create(['actif' => true])->assignRole('president');

        $this->actingAs($president)
            ->get(route('reporting.generer-async', ['type' => 'nomenclature', 'format' => 'pdf', 'exercice' => $this->exercice->id]))
            ->assertRedirect()
            ->assertSessionHas('succes');

        Queue::assertPushed(GenererRapportJob::class, fn ($job) => $job->type === 'nomenclature'
            && $job->format === 'pdf'
            && $job->exerciceId === $this->exercice->id);
    }

    public function test_async_refuse_sans_permission_generate(): void
    {
        Queue::fake();

        // « lecteur » : pas de reporting.generate (alias rapport.exporter).
        $this->actingAs(User::factory()->create(['actif' => true])->assignRole('lecteur'))
            ->get(route('reporting.generer-async', ['type' => 'nomenclature', 'exercice' => $this->exercice->id]))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_execution_du_job_archive_le_rapport(): void
    {
        Storage::fake('local');
        $president = User::factory()->create(['actif' => true])->assignRole('president');

        GenererRapportJob::dispatchSync('nomenclature', $this->exercice->id, 'pdf', [], $president->id);

        $rapport = Rapport::where('type', 'nomenclature')->where('format', 'pdf')->firstOrFail();
        Storage::disk('local')->assertExists($rapport->chemin);
        $this->assertSame($president->id, $rapport->genere_par);
    }
}
