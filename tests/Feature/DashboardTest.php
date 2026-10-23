<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_affiche_kpis_et_graphiques(): void
    {
        $this->seed(SafeDatabaseSeeder::class);
        $user = User::firstWhere('email', 'gestionnaire_budgetaire@ceeac.test');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Conteneurs des graphiques présents
        $response->assertSee('chartChaine', false);
        $response->assertSee('chartFeux', false);
        $response->assertSee('chartNature', false);
        $response->assertSee('chartEngagements', false);
        $response->assertSee('chartPilier', false);
        // KPIs et fil d'activité
        $response->assertSee("Taux d'engagement", false);
        $response->assertSee('Derniers engagements');
        $response->assertSee('Chaîne d\'exécution budgétaire', false);
    }

    public function test_dashboard_sans_donnees_affiche_message(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::create([
            'name' => 'Vide', 'email' => 'vide@ceeac.test', 'password' => bcrypt('x'),
            'actif' => true, 'statut' => 'actif',
        ]);
        $user->assignRole('lecteur');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Aucune donnée budgétaire');
    }
}
