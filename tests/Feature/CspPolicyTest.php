<?php

namespace Tests\Feature;

use App\Services\Security\CspPolicyBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** P3-4 — Durcissement CSP (politique + en-têtes). */
class CspPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
    }

    public function test_politique_standard_contient_les_directives_durcies(): void
    {
        $policy = app(CspPolicyBuilder::class)->enforce(Request::create('/login', 'GET'), 'abc123');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("'nonce-abc123'", $policy);
        $this->assertStringContainsString("'unsafe-inline'", $policy);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $policy);
    }

    public function test_route_api_docs_autorise_unpkg(): void
    {
        $route = (new \Illuminate\Routing\Route('GET', '/api/docs', []))->name('api.docs');
        $request = Request::create('/api/docs', 'GET');
        $request->setRouteResolver(fn () => $route);

        $policy = app(CspPolicyBuilder::class)->enforce($request, 'n');

        $this->assertStringContainsString('https://unpkg.com', $policy);
    }

    public function test_report_only_sans_unsafe_inline(): void
    {
        config(['security.csp.report_uri' => '/csp-report']);

        $policy = app(CspPolicyBuilder::class)->reportOnly(Request::create('/', 'GET'), 'nonce-test');

        $this->assertStringContainsString("'nonce-nonce-test'", $policy);
        $this->assertStringNotContainsString("'unsafe-inline'", $policy);
        $this->assertStringContainsString('report-uri /csp-report', $policy);
    }

    public function test_entetes_durcis_et_report_only_optionnel(): void
    {
        config(['security.csp.report_only' => true]);

        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_collecte_rapport_csp(): void
    {
        $this->postJson(route('csp.report'), [
            'csp-report' => [
                'document-uri' => 'http://localhost/login',
                'violated-directive' => 'script-src',
            ],
        ])->assertNoContent();
    }
}
