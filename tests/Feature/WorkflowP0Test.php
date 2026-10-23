<?php

namespace Tests\Feature;

use App\Models\Encaissement;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Recette;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Recettes\EncaissementService;
use App\Services\WorkflowService;
use App\Support\WorkflowPermissionAliases;
use App\Support\WorkflowRejetCategorie;
use Database\Seeders\MotifsRejetSeeder;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowPermissionsSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Phase W-P0 — permissions workflow, SoD, rejet catégorisé, circuit RECETTES. */
class WorkflowP0Test extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            RecettesPermissionsSeeder::class,
            WorkflowPermissionsSeeder::class,
            MotifsRejetSeeder::class,
            WorkflowSeeder::class,
        ]);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_permissions_workflow_sont_seedees(): void
    {
        $this->assertTrue(Permission::where('name', 'workflow.view')->exists());
        $this->assertTrue(Permission::where('name', 'workflow.validate')->exists());
        $this->assertTrue(Permission::where('name', 'workflow.reject')->exists());
    }

    public function test_alias_workflow_view_via_recette_voir(): void
    {
        $user = $this->user('comptable');
        $this->assertTrue(WorkflowPermissionAliases::evaluate($user, 'workflow.view'));
    }

    public function test_table_motifs_rejet_existe(): void
    {
        $this->assertTrue(Schema::hasTable('motifs_rejet'));
        $this->assertGreaterThan(0, \DB::table('motifs_rejet')->count());
    }

    public function test_rejet_exige_une_categorie(): void
    {
        $pap = $this->pap();
        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->expectException(RuntimeException::class);
        $this->workflow->rejeter($instance, $this->user('chef_service'), 'Données incomplètes.');
    }

    public function test_rejet_avec_categorie_enregistre_sur_etape(): void
    {
        $pap = $this->pap();
        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));

        $this->workflow->rejeter(
            $instance,
            $this->user('chef_service'),
            'Données incomplètes.',
            WorkflowRejetCategorie::DOCUMENTAIRE,
        );

        $etape = $instance->fresh()->etapes()->where('statut', 'rejete')->first();
        $this->assertSame(WorkflowRejetCategorie::DOCUMENTAIRE, $etape->categorie_rejet);
    }

    public function test_sod_interdit_deux_validations_consecutives_par_le_meme_user(): void
    {
        config(['workflow.sod.enabled' => true]);

        $pap = $this->pap();
        $instance = $this->workflow->demarrer($pap, 'PAP', $this->user('directeur'));
        $chef = $this->user('chef_service');

        $this->workflow->valider($instance->fresh(), $chef);

        $this->expectException(RuntimeException::class);
        $this->workflow->valider($instance->fresh(), $chef);
    }

    public function test_circuit_recettes_comptabilise_apres_trois_etapes(): void
    {
        $enc = $this->encaissementPret();
        $instance = $this->workflow->demarrer($enc, 'RECETTES', $this->user('comptable'));

        $this->assertSame(3, $instance->etapes()->count());
        $this->assertSame('en_validation', $enc->fresh()->statut);

        foreach (['gestionnaire_budgetaire', 'controleur_financier', 'comptable'] as $role) {
            $this->workflow->valider($instance->fresh(), $this->user($role));
        }

        $this->assertSame('valide', $instance->fresh()->statut);
        $this->assertSame('valide', $enc->fresh()->statut);
        $this->assertNotNull($enc->fresh()->valide_le);
    }

    public function test_circuit_recettes_rejet_enregistre_motif_sur_encaissement(): void
    {
        $enc = $this->encaissementPret();
        $instance = $this->workflow->demarrer($enc, 'RECETTES', $this->user('comptable'));

        $this->workflow->rejeter(
            $instance,
            $this->user('gestionnaire_budgetaire'),
            'Justificatif illisible.',
            WorkflowRejetCategorie::DOCUMENTAIRE,
        );

        $enc->refresh();
        $this->assertSame('rejete', $enc->statut);
        $this->assertSame('Justificatif illisible.', $enc->motif_rejet);
        $this->assertSame(WorkflowRejetCategorie::DOCUMENTAIRE, $enc->categorie_rejet);
    }

    public function test_workflow_recettes_est_seed(): void
    {
        $w = Workflow::where('code', 'RECETTES')->first();
        $this->assertNotNull($w);
        $this->assertSame('encaissement', $w->type_objet);
    }

    private function pap(): Pap
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        return Pap::create([
            'exercice_id' => $exercice->id,
            'reference' => 'PAP-W-P0',
            'libelle' => 'PAP test',
            'statut' => 'brouillon',
        ]);
    }

    private function encaissementPret(): Encaissement
    {
        $exercice = Exercice::create(['annee' => 2027, 'libelle' => 'Ex 2027', 'statut' => 'ouvert']);
        $titre = \App\Models\BudgetTitre::create(['code' => '7', 'intitule' => 'Recettes', 'sens' => 'recette', 'ordre' => 1]);
        $article = \App\Models\BudgetArticle::create(['titre_id' => $titre->id, 'code' => '72', 'intitule' => 'Contributions', 'ordre' => 1]);
        $paragraphe = \App\Models\BudgetParagraphe::create(['article_id' => $article->id, 'code' => '721', 'intitule' => 'États', 'ordre' => 1]);
        $rubrique = \App\Models\BudgetRubrique::create(['paragraphe_id' => $paragraphe->id, 'code' => '721010', 'intitule' => 'Contribution', 'ordre' => 1]);

        $recette = Recette::create([
            'exercice_id' => $exercice->id,
            'reference' => 'REC-W-P0',
            'rubrique_id' => $rubrique->id,
            'montant_prevu' => 1_000_000,
            'montant_realise' => 0,
            'statut' => 'prevue',
        ]);

        $user = $this->user('comptable');
        $enc = app(EncaissementService::class)->saisir([
            'recette_id' => $recette->id,
            'reference' => 'ENC-W-P0',
            'montant' => 500_000,
            'devise' => 'XAF',
            'taux_change' => 1,
            'mode_paiement' => 'virement',
            'date_encaissement' => '2026-04-01',
        ], $user);

        $enc->pieces()->create([
            'nom' => 'releve.pdf',
            'chemin' => 'ged/test/releve.pdf',
            'type_mime' => 'application/pdf',
            'taille' => 1024,
            'categorie' => 'justificatif',
            'televerse_par' => $user->id,
        ]);

        return $enc->fresh();
    }
}
