<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Mesure;
use App\Models\Pilier;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Models\User;
use App\Notifications\Rbm\MesureValideeNotification;
use App\Notifications\Rbm\MesureVerifieeNotification;
use Database\Seeders\Pap2026Seeder;
use Database\Seeders\RbmGranularPermissionsSeeder;
use Database\Seeders\RbmPermissionsSeeder;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** Tests phase 4 — export indicateurs, édition, notifications, sélecteur exercice. */
class RbmPhase4Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmPermissionsSeeder::class);
        $this->seed(RbmGranularPermissionsSeeder::class);
        $this->seed(RbmReferentielSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->seed(Pap2026Seeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    public function test_export_excel_indicateurs(): void
    {
        Excel::fake();

        $this->actingAs($this->admin())
            ->get(route('rbm.export.indicateurs', $this->exercice))
            ->assertOk();

        Excel::assertDownloaded('catalogue-indicateurs-2026.xlsx');
    }

    public function test_edition_indicateur(): void
    {
        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();
        $ind = Indicateur::create([
            'rbm_type' => Pilier::class,
            'rbm_id' => $pilier->id,
            'code' => 'IND-EDIT',
            'libelle' => 'Avant',
            'type' => 'strategique',
            'periodicite' => 'trimestriel',
        ]);

        $this->actingAs($this->admin())
            ->get(route('rbm.indicateurs.edit', $ind))
            ->assertOk()
            ->assertSee('IND-EDIT');

        $this->put(route('rbm.indicateurs.update', $ind), [
            'code' => 'IND-EDIT',
            'libelle' => 'Après modification',
            'type_indicateur_id' => TypeIndicateur::where('code', 'OPE')->value('id'),
            'unite_mesure_id' => UniteMesure::where('code', 'PCT')->value('id'),
            'periodicite' => 'annuel',
        ])->assertRedirect();

        $this->assertEquals('Après modification', $ind->fresh()->libelle);
        $this->assertEquals('operationnel', $ind->fresh()->type);
    }

    public function test_notification_mesure_verifiee(): void
    {
        Notification::fake();

        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();
        $ind = Indicateur::create([
            'rbm_type' => Pilier::class,
            'rbm_id' => $pilier->id,
            'code' => 'IND-NOTIF',
            'libelle' => 'Test notif',
            'type' => 'strategique',
            'periodicite' => 'trimestriel',
            'valeur_cible' => 100,
        ]);

        $validateur = User::factory()->create(['actif' => true]);
        $validateur->givePermissionTo(['indicateur.valider', 'indicateur.creer', 'rbm.voir']);

        $verificateur = User::factory()->create(['actif' => true]);
        $verificateur->givePermissionTo(['indicateur.valider', 'indicateur.creer', 'rbm.voir']);

        $this->actingAs($verificateur)
            ->post(route('rbm.mesures.store', $ind), [
                'periode' => '2026-T1',
                'valeur_realisee' => 80,
            ]);

        $mesure = Mesure::first();
        $this->actingAs($verificateur)
            ->post(route('rbm.mesures.verifier', $mesure))
            ->assertRedirect();

        Notification::assertSentTo($validateur, MesureVerifieeNotification::class);
    }

    public function test_notification_mesure_validee(): void
    {
        Notification::fake();

        $pilier = Pilier::where('exercice_id', $this->exercice->id)->first();
        $saisisseur = User::factory()->create(['actif' => true]);
        $saisisseur->givePermissionTo(['indicateur.creer', 'rbm.voir']);

        $ind = Indicateur::create([
            'rbm_type' => Pilier::class,
            'rbm_id' => $pilier->id,
            'code' => 'IND-VAL',
            'libelle' => 'Test validation',
            'type' => 'strategique',
            'periodicite' => 'trimestriel',
            'valeur_cible' => 100,
        ]);

        $this->actingAs($saisisseur)
            ->post(route('rbm.mesures.store', $ind), [
                'periode' => '2026-T1',
                'valeur_realisee' => 90,
            ]);

        $mesure = Mesure::first();
        $this->assertEquals($saisisseur->id, $mesure->saisi_par);

        $validateur = User::factory()->create(['actif' => true]);
        $validateur->givePermissionTo(['indicateur.valider', 'rbm.voir']);

        $this->actingAs($validateur)
            ->post(route('rbm.mesures.valider', $mesure))
            ->assertRedirect();

        Notification::assertSentTo($saisisseur, MesureValideeNotification::class);
    }

    public function test_arbre_avec_selecteur_exercices(): void
    {
        Exercice::create(['annee' => 2025, 'libelle' => 'Ex 2025', 'statut' => 'clos']);

        $this->actingAs($this->admin())
            ->get(route('rbm.arbre', $this->exercice))
            ->assertOk()
            ->assertSee('2026')
            ->assertSee('2025');
    }

    public function test_page_notifications(): void
    {
        $user = $this->admin();
        $user->notify(new MesureValideeNotification(
            Mesure::make(['periode' => '2026-T1', 'feu' => 'vert', 'taux_atteinte' => 80])
                ->setRelation('indicateur', Indicateur::make(['code' => 'X', 'rbm_type' => Pilier::class, 'rbm_id' => 1]))
        ));

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Mesure IOV validée');
    }
}
