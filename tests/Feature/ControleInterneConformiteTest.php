<?php

namespace Tests\Feature;

use App\Models\ControleInterne;
use App\Models\NonConformite;
use App\Models\User;
use App\Services\Gouvernance\GouvernanceService;
use Database\Seeders\AuditControleConformiteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot C — contrôle interne COSO + non-conformités / plans de remédiation. */
class ControleInterneConformiteTest extends TestCase
{
    use RefreshDatabase;

    private User $auditeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AuditControleConformiteSeeder::class);

        $this->auditeur = User::create([
            'name' => 'Auditeur CI', 'email' => 'aud.ci@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->auditeur->assignRole('auditeur');
    }

    public function test_controle_coso_creation_et_evaluation(): void
    {
        $this->actingAs($this->auditeur)->post(route('controle-interne.store'), [
            'composante' => 'activites_controle', 'libelle' => 'Visa CF avant engagement',
            'type' => 'preventif', 'frequence' => 'permanent', 'evaluation' => 'non_evalue',
        ])->assertRedirect(route('controle-interne.index'));

        $controle = ControleInterne::firstWhere('libelle', 'Visa CF avant engagement');
        $this->assertNotNull($controle);
        $this->assertStringStartsWith('CI-', $controle->code);
        $this->assertSame('activites_controle', $controle->composante);

        // Évaluation d'efficacité.
        $this->actingAs($this->auditeur)->post(route('controle-interne.evaluer', $controle), ['evaluation' => 'efficace'])
            ->assertSessionHas('succes');
        $this->assertSame('efficace', $controle->fresh()->evaluation);
        $this->assertNotNull($controle->fresh()->date_evaluation);
    }

    public function test_cartographie_coso_accessible(): void
    {
        ControleInterne::create(['code' => 'CI-1', 'composante' => 'pilotage', 'libelle' => 'Revue trimestrielle', 'type' => 'detectif', 'frequence' => 'trimestriel', 'evaluation' => 'efficace']);

        $this->actingAs($this->auditeur)->get(route('controle-interne.index'))
            ->assertOk()->assertSee('COSO')->assertSee('Pilotage / surveillance');
    }

    public function test_non_conformite_cycle_remediation(): void
    {
        $this->actingAs($this->auditeur)->post(route('conformite.store'), [
            'source' => 'controle_interne', 'libelle' => 'Engagement sans visa', 'gravite' => 'elevee',
            'plan_action' => 'Renforcer le contrôle', 'statut' => 'ouverte', 'taux_avancement' => 0,
        ])->assertRedirect();

        $nc = NonConformite::firstWhere('libelle', 'Engagement sans visa');
        $this->assertNotNull($nc);
        $this->assertStringStartsWith('NC-', $nc->code);

        // Suivi de la remédiation → résolution.
        $this->actingAs($this->auditeur)->post(route('conformite.suivi', $nc), [
            'statut' => 'resolue', 'taux_avancement' => 100,
        ])->assertSessionHas('succes');
        $this->assertSame('resolue', $nc->fresh()->statut);

        // Taux de conformité = 100% (1 NC résolue sur 1).
        $this->assertSame(100.0, app(GouvernanceService::class)->conformite()['taux_conformite']);
    }

    public function test_permissions_controle_interne(): void
    {
        $lecteur = User::create([
            'name' => 'Lecteur CI', 'email' => 'lect.ci@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        $this->actingAs($lecteur)->get(route('controle-interne.index'))->assertOk();       // voir via *.voir
        $this->actingAs($lecteur)->get(route('conformite.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('controle-interne.create'))->assertForbidden(); // creer requis
        $this->actingAs($lecteur)->get(route('conformite.create'))->assertForbidden();
    }
}
