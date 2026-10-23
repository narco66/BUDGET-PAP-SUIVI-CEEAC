<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use App\Services\Reporting\RapportComposer;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** P3-2 — Rapports techniques (santé, intégrité, audit). */
class RapportTechniqueTest extends TestCase
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

    public function test_hub_techniques_accessible_admin(): void
    {
        $this->actingAs($this->admin)->get(route('reporting.techniques'))
            ->assertOk()
            ->assertSee('Rapports techniques');
    }

    public function test_compose_les_trois_types(): void
    {
        $composer = app(RapportComposer::class);
        foreach (['technique_sante', 'technique_integrite', 'technique_audit'] as $type) {
            $c = $composer->composer($type, $this->exercice);
            $this->assertNotEmpty($c['sections']);
        }
    }

    public function test_generation_pdf_technique_sante(): void
    {
        Storage::fake('local');
        config(['reporting.async.enabled' => false]);

        $this->actingAs($this->admin)->get(route('reporting.generer', [
            'type' => 'technique_sante', 'format' => 'pdf', 'exercice' => $this->exercice->id,
        ]))->assertOk();

        $this->assertSame(1, Rapport::where('type', 'technique_sante')->count());
    }

    public function test_lecteur_refuse_rapport_technique(): void
    {
        $lecteur = User::firstWhere('email', 'lecteur@ceeac.test');
        $this->actingAs($lecteur)->get(route('reporting.techniques'))->assertForbidden();
        $this->actingAs($lecteur)->get(route('reporting.show', [
            'type' => 'technique_sante', 'exercice' => $this->exercice->id,
        ]))->assertForbidden();
    }
}
