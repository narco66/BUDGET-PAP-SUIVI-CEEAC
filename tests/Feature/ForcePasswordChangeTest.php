<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilisateur_a_changer_est_redirige(): void
    {
        $u = User::factory()->create(['actif' => true, 'statut' => 'actif', 'doit_changer_mot_de_passe' => true]);

        $this->actingAs($u)->get(route('dashboard'))->assertRedirect(route('password.edit'));
        $this->actingAs($u)->get(route('password.edit'))->assertOk(); // accessible
    }

    public function test_login_echoue_est_journalise(): void
    {
        User::factory()->create(['email' => 'jean@ceeac.test', 'password' => Hash::make('bon-mdp'), 'actif' => true, 'statut' => 'actif']);

        $this->post(route('login'), ['email' => 'jean@ceeac.test', 'password' => 'mauvais'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('login_logs', ['email' => 'jean@ceeac.test', 'succes' => false]);
    }

    public function test_login_reussi_est_journalise_et_horodate(): void
    {
        $u = User::factory()->create(['email' => 'marie@ceeac.test', 'password' => Hash::make('bon-mdp'), 'actif' => true, 'statut' => 'actif', 'doit_changer_mot_de_passe' => false]);

        $this->post(route('login'), ['email' => 'marie@ceeac.test', 'password' => 'bon-mdp'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('login_logs', ['user_id' => $u->id, 'succes' => true]);
        $this->assertNotNull($u->fresh()->derniere_connexion_at);
    }

    public function test_compte_suspendu_ne_peut_pas_se_connecter(): void
    {
        User::factory()->create(['email' => 'susp@ceeac.test', 'password' => Hash::make('bon-mdp'), 'actif' => false, 'statut' => 'suspendu']);

        $this->post(route('login'), ['email' => 'susp@ceeac.test', 'password' => 'bon-mdp'])
            ->assertSessionHasErrors('email');
    }
}
