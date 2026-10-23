<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Models\User;
use App\Services\Ged\GedAttachmentService;
use App\Services\Ged\GedDocumentService;
use App\Services\Rbm\RbmDuplicationService;
use Database\Seeders\RbmReferentielSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase E8 — GED nœuds RBM, duplication inter-exercices, API REST. */
class RbmPhaseE8Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RbmReferentielSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['actif' => true])->assignRole('administrateur');
    }

    private function chaine(Exercice $exercice): Activite
    {
        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P1', 'intitule' => 'P']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $prod = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $prod->id, 'code' => 'SP1', 'intitule' => 'SP']);

        return Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $prod->id,
            'code' => 'ACT1',
            'intitule' => 'Activité test',
            'montant_total' => 1000,
        ]);
    }

    public function test_ged_rattachement_activite_rbm(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $act = $this->chaine($exercice);
        $user = $this->admin();

        $doc = app(GedAttachmentService::class)->rattacherDepuisModule(
            'activite',
            $act->id,
            UploadedFile::fake()->create('iov.pdf', 40, 'application/pdf'),
            ['titre' => 'Pièce activité'],
            $user,
            app(GedDocumentService::class),
        );

        $this->assertSame(Activite::class, $doc->attachable_type);
        $this->assertSame('RBM', $doc->module_source);
        $this->assertSame($act->id, $doc->attachable_id);
    }

    public function test_page_ged_activite_accessible(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $act = $this->chaine($exercice);
        $user = $this->admin();
        $user->givePermissionTo('ged.voir');

        $this->actingAs($user)
            ->get(route('rbm.ged.show', ['activite', $act->id]))
            ->assertOk()
            ->assertSee('Activité RBM');
    }

    public function test_duplication_referentiel_inter_exercices(): void
    {
        $source = Exercice::create(['annee' => 2025, 'libelle' => 'N-1', 'statut' => 'cloture']);
        $cible = Exercice::create(['annee' => 2026, 'libelle' => 'N', 'statut' => 'ouvert']);
        $act = $this->chaine($source);

        Indicateur::create([
            'rbm_type' => Activite::class,
            'rbm_id' => $act->id,
            'code' => 'IND-1',
            'libelle' => 'IOV test',
            'type' => 'operationnel',
            'type_indicateur_id' => TypeIndicateur::where('code', 'OPE')->value('id'),
            'unite_mesure_id' => UniteMesure::where('code', 'PCT')->value('id'),
            'unite' => '%',
            'periodicite' => 'trimestriel',
            'valeur_cible' => 100,
        ]);

        $stats = app(RbmDuplicationService::class)->dupliquerReferentiel($source, $cible, $this->admin());

        $this->assertSame(1, $stats['piliers']);
        $this->assertSame(1, $stats['activites']);
        $this->assertSame(1, $stats['indicateurs']);
        $this->assertDatabaseHas('piliers', ['exercice_id' => $cible->id, 'code' => 'P1']);
        $this->assertNull(Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $cible->id))->first()->ligne_budgetaire_id);
    }

    public function test_commande_reporter_exercice(): void
    {
        $this->admin();
        $source = Exercice::create(['annee' => 2024, 'libelle' => 'S', 'statut' => 'cloture']);
        $cible = Exercice::create(['annee' => 2027, 'libelle' => 'C', 'statut' => 'ouvert']);
        $this->chaine($source);

        $this->artisan('rbm:reporter-exercice', ['--source' => 2024, '--cible' => 2027])
            ->assertSuccessful();

        $this->assertSame(1, Pilier::where('exercice_id', $cible->id)->count());
    }

    public function test_api_rbm_piliers_avec_scope_lecture(): void
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);
        $this->chaine($exercice);

        Sanctum::actingAs(User::factory()->create(['actif' => true]), ['lecture']);

        $this->getJson(route('api.rbm.piliers', ['exercice' => $exercice->id]))
            ->assertOk()
            ->assertJsonFragment(['code' => 'P1']);
    }

    public function test_api_rbm_matrice_exige_exercice(): void
    {
        Sanctum::actingAs(User::factory()->create(['actif' => true]), ['lecture']);

        $this->getJson(route('api.rbm.matrice'))->assertStatus(422);
    }
}
