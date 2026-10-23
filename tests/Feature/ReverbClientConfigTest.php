<?php

namespace Tests\Feature;

use App\Support\ReverbClientConfig;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Reverb / Echo — configuration client et CSP (P3-7 prod). */
class ReverbClientConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
    }

    public function test_reverb_desactive_par_defaut(): void
    {
        config([
            'broadcasting.default' => 'log',
            'cockpit.live.reverb.enabled' => false,
        ]);

        $cfg = ReverbClientConfig::pourFront();

        $this->assertFalse($cfg['enabled']);
        $this->assertSame([], ReverbClientConfig::cspConnectSources());
    }

    public function test_reverb_active_ajoute_sources_csp(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'cockpit.live.reverb.enabled' => true,
            'cockpit.live.reverb.host' => 'ws.ceeac.local',
            'cockpit.live.reverb.port' => 443,
            'cockpit.live.reverb.scheme' => 'https',
        ]);

        $cfg = ReverbClientConfig::pourFront();

        $this->assertTrue($cfg['enabled']);
        $this->assertSame('test-key', $cfg['key']);
        $sources = ReverbClientConfig::cspConnectSources();
        $this->assertContains('https://ws.ceeac.local:443', $sources);
        $this->assertContains('wss://ws.ceeac.local:443', $sources);
    }
}
