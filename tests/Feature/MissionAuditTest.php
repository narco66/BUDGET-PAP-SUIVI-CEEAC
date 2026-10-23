<?php

namespace Tests\Feature;

use App\Models\ConstatAudit;
use App\Models\MissionAudit;
use App\Models\User;
use Database\Seeders\AuditControleConformiteSeeder;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot B — missions d'audit (INTOSAI) : planification, constats, recommandations, suivi, clôture. */
class MissionAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $auditeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AuditControleConformiteSeeder::class);
        $this->seed(ExerciceSeeder::class);

        $this->auditeur = User::create([
            'name' => 'Auditeur', 'email' => 'aud@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->auditeur->assignRole('auditeur');
    }

    private function mission(string $statut = 'en_cours'): MissionAudit
    {
        return MissionAudit::create([
            'numero' => 'MA-TEST-'.uniqid(), 'type' => 'interne',
            'libelle' => 'Audit de la chaîne de la dépense', 'statut' => $statut,
        ]);
    }

    public function test_auditeur_peut_planifier_une_mission(): void
    {
        $this->actingAs($this->auditeur)->post(route('audit-missions.store'), [
            'type' => 'conformite', 'libelle' => 'Audit conformité budgétaire', 'statut' => 'planifiee',
        ])->assertRedirect();

        $mission = MissionAudit::firstWhere('libelle', 'Audit conformité budgétaire');
        $this->assertNotNull($mission);
        $this->assertStringStartsWith('MA-', $mission->numero);
    }

    public function test_cycle_constat_recommandation_et_suivi(): void
    {
        $mission = $this->mission();

        $this->actingAs($this->auditeur)->post(route('audit-missions.constats.store', $mission), [
            'libelle' => 'Engagement sans pièce justificative', 'gravite' => 'elevee',
            'recommandation' => 'Exiger la pièce avant visa', 'statut' => 'ouvert', 'taux_avancement' => 0,
        ])->assertRedirect();

        $constat = ConstatAudit::firstWhere('mission_audit_id', $mission->id);
        $this->assertNotNull($constat);
        $this->assertStringStartsWith('CA-', $constat->numero);

        // Suivi de la recommandation : avancement.
        $this->actingAs($this->auditeur)->put(route('audit-missions.constats.update', [$mission, $constat]), [
            'statut' => 'en_cours', 'taux_avancement' => 60,
        ])->assertRedirect();
        $this->assertSame(60, $constat->fresh()->taux_avancement);
    }

    public function test_cloture_bloquee_tant_que_des_recommandations_sont_ouvertes(): void
    {
        $mission = $this->mission();
        ConstatAudit::create([
            'numero' => 'CA-X', 'mission_audit_id' => $mission->id, 'libelle' => 'Constat ouvert',
            'gravite' => 'moyenne', 'statut' => 'ouvert', 'taux_avancement' => 0,
        ]);

        // Clôture refusée (constat ouvert).
        $this->actingAs($this->auditeur)->post(route('audit-missions.cloturer', $mission), [
            'conclusion' => 'RAS', 'score_conformite' => 70,
        ])->assertSessionHas('erreur');
        $this->assertSame('en_cours', $mission->fresh()->statut);

        // On solde le constat puis on clôture.
        $mission->constats()->first()->update(['statut' => 'realise', 'taux_avancement' => 100]);
        $this->actingAs($this->auditeur)->post(route('audit-missions.cloturer', $mission), [
            'conclusion' => 'Mission clôturée, recommandations soldées.', 'score_conformite' => 85,
        ])->assertSessionHas('succes');
        $this->assertSame('cloturee', $mission->fresh()->statut);
    }

    public function test_permissions_planification(): void
    {
        $lecteur = User::create([
            'name' => 'Lecteur', 'email' => 'lect.ma@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        $this->actingAs($lecteur)->get(route('audit-missions.index'))->assertOk();       // audit.voir via *.voir
        $this->actingAs($lecteur)->get(route('audit-missions.create'))->assertForbidden(); // audit.creer requis
    }
}
