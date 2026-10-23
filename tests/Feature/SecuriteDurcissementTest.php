<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\User;
use App\Services\AdministrationTechnique\ProtectionConnexionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot T4 — durcissement sécurité : verrouillage anti brute-force + en-têtes de sécurité. */
class SecuriteDurcissementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_verrouillage_apres_cinq_echecs(): void
    {
        $protection = app(ProtectionConnexionService::class);
        $ip = '203.0.113.7';

        // 4 échecs → pas encore bloqué.
        for ($i = 0; $i < 4; $i++) {
            \App\Models\LoginLog::create(['email' => 'x@y.test', 'succes' => false, 'ip' => $ip, 'created_at' => now()]);
        }
        $this->assertNull($protection->enregistrerEchec($ip));
        $this->assertNull($protection->ipBloquee($ip));

        // 5ᵉ échec → IP verrouillée.
        \App\Models\LoginLog::create(['email' => 'x@y.test', 'succes' => false, 'ip' => $ip, 'created_at' => now()]);
        $this->assertNotNull($protection->enregistrerEchec($ip));
        $this->assertNotNull($protection->ipBloquee($ip));
        $this->assertDatabaseHas('blocked_ips', ['ip' => $ip, 'permanent' => false]);

        // Connexion réussie → réinitialisation du verrou automatique.
        $protection->reinitialiser($ip);
        $this->assertNull($protection->ipBloquee($ip));
    }

    public function test_login_http_bloque_apres_le_seuil(): void
    {
        // 5 tentatives échouées via HTTP.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => 'pirate@ceeac.test', 'password' => 'faux'])
                ->assertSessionHasErrors('email');
        }

        $this->assertNotNull(app(ProtectionConnexionService::class)->ipBloquee('127.0.0.1'));

        // 6ᵉ tentative : message de verrouillage.
        $this->post(route('login'), ['email' => 'pirate@ceeac.test', 'password' => 'faux'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseHas('blocked_ips', ['ip' => '127.0.0.1']);
    }

    public function test_blocage_manuel_permanent(): void
    {
        $admin = User::create(['name' => 'Adm', 'email' => 'adm.sec@ceeac.test', 'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif']);
        $bloc = app(ProtectionConnexionService::class)->bloquerManuel('198.51.100.4', 'Tentative suspecte', $admin->id, true);

        $this->assertTrue($bloc->permanent);
        $this->assertTrue($bloc->estActif());
        $this->assertNotNull(app(ProtectionConnexionService::class)->ipBloquee('198.51.100.4'));
    }

    public function test_entetes_de_securite_presents(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    /** Alpine.js (build standard) évalue ses directives via new Function() → la CSP doit autoriser 'unsafe-eval'. */
    public function test_csp_autorise_unsafe_eval_pour_alpine(): void
    {
        $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'unsafe-eval'", $csp);

        // Désactivable explicitement (migration future vers le build CSP d'Alpine).
        config(['security.csp.allow_eval' => false]);
        $cspSans = app(\App\Services\Security\CspPolicyBuilder::class)->enforce(request());
        $this->assertStringNotContainsString("'unsafe-eval'", $cspSans);
    }
}
