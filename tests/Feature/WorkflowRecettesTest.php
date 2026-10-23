<?php

namespace Tests\Feature;

use App\Models\Encaissement;
use App\Models\Exercice;
use App\Models\Recette;
use App\Models\User;
use App\Services\Recettes\EncaissementService;
use App\Services\WorkflowService;
use App\Support\WorkflowRejetCategorie;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Circuit RECETTES — encaissements (P1). */
class WorkflowRecettesTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class, WorkflowSeeder::class]);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function encaissement(): Encaissement
    {
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $titre = \App\Models\BudgetTitre::create(['code' => '7', 'intitule' => 'Recettes', 'sens' => 'recette', 'ordre' => 1]);
        $article = \App\Models\BudgetArticle::create(['titre_id' => $titre->id, 'code' => '72', 'intitule' => 'C', 'ordre' => 1]);
        $paragraphe = \App\Models\BudgetParagraphe::create(['article_id' => $article->id, 'code' => '721', 'intitule' => 'E', 'ordre' => 1]);
        $rubrique = \App\Models\BudgetRubrique::create(['paragraphe_id' => $paragraphe->id, 'code' => '721010', 'intitule' => 'R', 'ordre' => 1]);

        $recette = Recette::create([
            'exercice_id' => $exercice->id,
            'reference' => 'REC-WF',
            'rubrique_id' => $rubrique->id,
            'montant_prevu' => 2_000_000,
            'montant_realise' => 0,
            'statut' => 'prevue',
        ]);

        $saisisseur = $this->user('comptable');
        $enc = app(EncaissementService::class)->saisir([
            'recette_id' => $recette->id,
            'reference' => 'ENC-WF',
            'montant' => 800_000,
            'devise' => 'XAF',
            'taux_change' => 1,
            'mode_paiement' => 'virement',
            'date_encaissement' => '2026-05-01',
        ], $saisisseur);

        $enc->pieces()->create([
            'nom' => 'j.pdf', 'chemin' => 't/j.pdf', 'type_mime' => 'application/pdf',
            'taille' => 100, 'categorie' => 'justificatif', 'televerse_par' => $saisisseur->id,
        ]);

        return $enc->fresh();
    }

    public function test_circuit_recettes_trois_etapes(): void
    {
        $enc = $this->encaissement();
        $instance = $this->workflow->demarrer($enc, 'RECETTES', $this->user('comptable'));

        $this->assertCount(3, $instance->etapes);
        $this->assertSame('en_validation', $enc->fresh()->statut);
    }

    public function test_comptabilisation_fin_de_circuit(): void
    {
        $enc = $this->encaissement();
        $instance = $this->workflow->demarrer($enc, 'RECETTES', $this->user('comptable'));

        foreach (['gestionnaire_budgetaire', 'controleur_financier', 'comptable'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            $this->workflow->valider($instance->fresh(), $user);
        }

        $this->assertSame('valide', $enc->fresh()->statut);
        $this->assertEqualsWithDelta(800_000.0, (float) $enc->recette->fresh()->montant_realise, 0.01);
    }

    public function test_echeance_posee_sur_etape_courante(): void
    {
        $enc = $this->encaissement();
        $instance = $this->workflow->demarrer($enc, 'RECETTES', $this->user('comptable'));

        $etape = $instance->fresh()->etapeCourante();
        $this->assertNotNull($etape?->echeance_le);
        $this->assertTrue($etape->echeance_le->isFuture());
    }

    public function test_rejet_recettes_avec_motif(): void
    {
        $enc = $this->encaissement();
        $instance = $this->workflow->demarrer($enc, 'RECETTES', $this->user('comptable'));

        $this->workflow->rejeter(
            $instance,
            $this->user('gestionnaire_budgetaire'),
            'Pièce illisible.',
            WorkflowRejetCategorie::DOCUMENTAIRE,
        );

        $enc->refresh();
        $this->assertSame('rejete', $enc->statut);
        $this->assertSame('Pièce illisible.', $enc->motif_rejet);
    }
}
