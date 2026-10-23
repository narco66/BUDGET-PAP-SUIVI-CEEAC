<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentCategorie;
use App\Models\User;
use Database\Seeders\AdministrationTechniqueSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot T2 — gestion des incidents (ITIL : déclaration → diagnostic → correction → validation → clôture). */
class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private User $auditeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdministrationTechniqueSeeder::class);

        $this->auditeur = User::create([
            'name' => 'Exploit', 'email' => 'exploit@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->auditeur->assignRole('auditeur');
    }

    public function test_seeder_cree_les_categories(): void
    {
        $this->assertSame(5, IncidentCategorie::count());
        $this->assertDatabaseHas('incident_categories', ['code' => 'SECU']);
    }

    public function test_declaration_puis_cycle_complet(): void
    {
        $cat = IncidentCategorie::firstWhere('code', 'DISPO');

        $this->actingAs($this->auditeur)->post(route('incidents.store'), [
            'titre' => 'Indisponibilité du serveur applicatif', 'gravite' => 'critique',
            'source' => 'monitoring', 'incident_categorie_id' => $cat->id,
        ])->assertRedirect();

        $incident = Incident::firstWhere('titre', 'Indisponibilité du serveur applicatif');
        $this->assertNotNull($incident);
        $this->assertStringStartsWith('INC-', $incident->numero);
        $this->assertSame('ouvert', $incident->statut);
        $this->assertSame(1, $incident->actions()->count()); // action de déclaration

        // Diagnostic → Correction → Validation → Résolu (4 avancements).
        foreach (['en_diagnostic', 'en_correction', 'en_validation', 'resolu'] as $attendu) {
            $this->actingAs($this->auditeur)->post(route('incidents.avancer', $incident), ['observation' => 'étape'])->assertSessionHas('succes');
            $this->assertSame($attendu, $incident->fresh()->statut);
        }
        $this->assertNotNull($incident->fresh()->date_resolution);

        // Clôture.
        $this->actingAs($this->auditeur)->post(route('incidents.cloturer', $incident), [
            'resolution' => 'Redémarrage du service + correctif appliqué.', 'cause_racine' => 'Fuite mémoire',
        ])->assertSessionHas('succes');
        $this->assertSame('clos', $incident->fresh()->statut);
        $this->assertDatabaseHas('incident_actions', ['incident_id' => $incident->id, 'type' => 'cloture']);
    }

    public function test_avancement_bloque_si_clos(): void
    {
        $incident = Incident::create(['numero' => 'INC-X', 'titre' => 'Clos', 'gravite' => 'faible', 'source' => 'manuelle', 'statut' => 'clos']);
        $this->actingAs($this->auditeur)->post(route('incidents.avancer', $incident))->assertSessionHas('erreur');
    }

    public function test_rejet_d_un_incident(): void
    {
        $incident = Incident::create(['numero' => 'INC-Y', 'titre' => 'Faux positif', 'gravite' => 'faible', 'source' => 'alerte', 'statut' => 'ouvert']);
        $this->actingAs($this->auditeur)->post(route('incidents.rejeter', $incident), ['motif' => 'Non reproductible'])->assertSessionHas('succes');
        $this->assertSame('rejete', $incident->fresh()->statut);
    }

    public function test_separation_des_droits(): void
    {
        // Le gestionnaire peut déclarer mais pas clôturer (incident.valider requis).
        $gestionnaire = User::create(['name' => 'Gest', 'email' => 'gest.inc@ceeac.test', 'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif']);
        $gestionnaire->assignRole('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)->get(route('incidents.create'))->assertOk(); // incident.creer
        $incident = Incident::create(['numero' => 'INC-Z', 'titre' => 'Test', 'gravite' => 'moyenne', 'source' => 'manuelle', 'statut' => 'resolu']);
        $this->actingAs($gestionnaire)->post(route('incidents.cloturer', $incident), ['resolution' => 'x'])->assertForbidden();

        // Lecteur : consultation OK, déclaration interdite.
        $lecteur = User::create(['name' => 'Lec', 'email' => 'lec.inc@ceeac.test', 'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif']);
        $lecteur->assignRole('lecteur');
        $this->actingAs($lecteur)->get(route('incidents.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('incidents.create'))->assertForbidden();
    }
}
