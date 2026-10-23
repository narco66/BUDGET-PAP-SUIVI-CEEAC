<?php

namespace Tests\Feature;

use App\Events\CockpitMetricsUpdated;
use App\Models\Exercice;
use App\Models\User;
use App\Services\Reporting\CockpitLiveService;
use App\Support\CockpitCache;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** P3-7 — Drill-down live cockpit exécutif (JSON + broadcast). */
class CockpitLiveP3Test extends TestCase
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

    public function test_snapshot_json_accessible(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('reporting.cockpit.live.snapshot', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cache_version', 'exercice_id', 'global', 'pap', 'series', 'alertes_count',
                ],
            ]);
    }

    public function test_ping_detecte_changement_version(): void
    {
        $avant = CockpitCache::version();

        $this->actingAs($this->admin)
            ->getJson(route('reporting.cockpit.live.ping', ['since' => $avant]))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        CockpitCache::bump($this->exercice->id);

        $this->actingAs($this->admin)
            ->getJson(route('reporting.cockpit.live.ping', ['since' => $avant]))
            ->assertOk()
            ->assertJsonPath('data.changed', true);
    }

    public function test_bump_emet_event_broadcast(): void
    {
        Event::fake([CockpitMetricsUpdated::class]);

        CockpitCache::bump($this->exercice->id, ['budget']);

        Event::assertDispatched(CockpitMetricsUpdated::class, function (CockpitMetricsUpdated $e) {
            return $e->exerciceId === $this->exercice->id
                && $e->cacheVersion === CockpitCache::version()
                && in_array('budget', $e->domains, true);
        });
    }

    public function test_drilldown_categorie_retourne_structure(): void
    {
        $snapshot = app(CockpitLiveService::class)->snapshot($this->exercice->id);
        $code = $snapshot['series']['categories'][0]['code'] ?? null;

        if (! $code || $code === '—') {
            $this->markTestSkipped('Aucune catégorie budgétaire en jeu de démo.');
        }

        $this->actingAs($this->admin)
            ->getJson(route('reporting.cockpit.live.drilldown', [
                'exercice' => $this->exercice->id,
                'niveau' => 'categorie',
                'parent' => $code,
            ]))
            ->assertOk()
            ->assertJsonPath('data.niveau', 'categorie')
            ->assertJsonStructure(['data' => ['lignes']]);
    }

    public function test_refuse_sans_droit(): void
    {
        $user = User::factory()->create(['actif' => true]);

        $this->actingAs($user)
            ->getJson(route('reporting.cockpit.live.snapshot', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }

    public function test_cockpit_affiche_badge_live(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reporting.cockpit', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('cockpit-live-badge', false)
            ->assertSee('cockpit-drill-panel', false);
    }
}
