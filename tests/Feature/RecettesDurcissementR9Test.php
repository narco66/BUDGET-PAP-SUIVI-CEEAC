<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\Recette;
use App\Models\User;
use App\Services\Recettes\EncaissementService;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** Phase R9 — durcissement ventilation (rubrique obligatoire) + intégration tableau de bord. */
class RecettesDurcissementR9Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private BudgetRubrique $rubrique;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        $titre = BudgetTitre::create(['code' => '7', 'intitule' => 'Recettes', 'sens' => 'recette', 'ordre' => 1]);
        $article = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '72', 'intitule' => 'Contributions', 'ordre' => 1]);
        $paragraphe = BudgetParagraphe::create(['article_id' => $article->id, 'code' => '721', 'intitule' => 'États', 'ordre' => 1]);
        $this->rubrique = BudgetRubrique::create(['paragraphe_id' => $paragraphe->id, 'code' => '721010', 'intitule' => 'Contribution', 'ordre' => 1]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function recette(?int $rubriqueId): Recette
    {
        return Recette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-'.uniqid(),
            'rubrique_id' => $rubriqueId,
            'montant_prevu' => 5_000_000,
            'montant_realise' => 0,
            'statut' => 'prevue',
        ]);
    }

    private function encaissementSaisi(Recette $recette): \App\Models\Encaissement
    {
        $service = app(EncaissementService::class);
        $user = $this->user('comptable');
        $enc = $service->saisir([
            'recette_id' => $recette->id, 'reference' => 'ENC-'.uniqid(),
            'montant' => 2_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $user);
        $enc->pieces()->create([
            'nom' => 'r.pdf', 'chemin' => 'ged/r.pdf', 'type_mime' => 'application/pdf',
            'taille' => 100, 'categorie' => 'justificatif', 'televerse_par' => $user->id,
        ]);

        return $enc;
    }

    public function test_validation_bloquee_si_recette_non_ventilee(): void
    {
        $enc = $this->encaissementSaisi($this->recette(null));

        $this->expectException(RuntimeException::class);
        app(EncaissementService::class)->valider($enc, $this->user('controleur_financier'));
    }

    public function test_validation_ok_apres_ventilation(): void
    {
        $recette = $this->recette($this->rubrique->id);
        $enc = $this->encaissementSaisi($recette);

        app(EncaissementService::class)->valider($enc, $this->user('controleur_financier'));

        $this->assertSame('valide', $enc->fresh()->statut);
        $this->assertEquals(2_000_000.0, (float) $recette->fresh()->montant_realise);
    }

    public function test_action_ventiler_via_http(): void
    {
        $recette = $this->recette(null);

        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->post(route('recettes.ventiler', $recette), ['rubrique_id' => $this->rubrique->id])
            ->assertRedirect();

        $this->assertSame($this->rubrique->id, $recette->fresh()->rubrique_id);
    }

    public function test_tableau_de_bord_affiche_la_section_recettes(): void
    {
        $response = $this->actingAs($this->user('lecteur'))->get(route('tableaux.index', ['exercice' => $this->exercice->id]));
        $response->assertOk();
        $response->assertSee('Recettes & ressources', false);
    }
}
