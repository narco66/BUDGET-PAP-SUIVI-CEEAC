<?php

namespace Tests\Feature;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Encaissement;
use App\Models\Exercice;
use App\Models\Recette;
use App\Models\User;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecettesSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private BudgetRubrique $rubrique;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $titre = BudgetTitre::create(['code' => '7', 'intitule' => 'Recettes', 'sens' => 'recette', 'ordre' => 1]);
        $article = BudgetArticle::create(['titre_id' => $titre->id, 'code' => '72', 'intitule' => 'C', 'ordre' => 1]);
        $paragraphe = BudgetParagraphe::create(['article_id' => $article->id, 'code' => '721', 'intitule' => 'E', 'ordre' => 1]);
        $this->rubrique = BudgetRubrique::create(['paragraphe_id' => $paragraphe->id, 'code' => '721010', 'intitule' => 'R', 'ordre' => 1]);
        Notification::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function recette(array $attrs = []): Recette
    {
        return Recette::create(array_merge([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-SPA',
            'montant_prevu' => 2_000_000,
            'montant_realise' => 0,
            'statut' => 'prevue',
        ], $attrs));
    }

    public function test_lecteur_liste_sans_saisir_ni_ventiler(): void
    {
        $this->recette();

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.recettes.index', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.0.montant_prevu', '2000000.00')
            ->assertJsonPath('data.0.actions.ventiler', false)
            ->assertJsonPath('synthese.montant_realise', '0.00');

        $recette = Recette::first();
        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.recettes.ventiler', $recette), ['rubrique_id' => $this->rubrique->id])
            ->assertForbidden();

        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.recettes.encaissements.store'), [
                'recette_id' => $recette->id,
                'reference' => 'ENC-X',
                'montant' => 100,
                'devise' => 'XAF',
                'taux_change' => 1,
                'mode_paiement' => 'virement',
                'date_encaissement' => '2026-05-01',
            ])
            ->assertForbidden();
    }

    public function test_parcours_api_ventilation_encaissement_et_circuit(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $comptable = $this->user('comptable');
        $comptableVisa = $this->user('comptable');
        $cf = $this->user('controleur_financier');
        $recette = $this->recette();

        $this->actingAs($gestionnaire)
            ->postJson(route('api.recettes.ventiler', $recette), ['rubrique_id' => $this->rubrique->id])
            ->assertOk()
            ->assertJsonPath('recette.rubrique', '721010');

        $this->actingAs($comptable)
            ->getJson(route('api.recettes.encaissements.options', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('recettes.0.montant_prevu', '2000000.00');

        $this->actingAs($comptable)->postJson(route('api.recettes.encaissements.store'), [
            'recette_id' => $recette->id,
            'reference' => 'ENC-SPA-1',
            'montant' => 800000,
            'devise' => 'XAF',
            'taux_change' => 1,
            'mode_paiement' => 'virement',
            'date_encaissement' => '2026-05-01',
        ])->assertCreated()->assertJsonPath('encaissement.montant_xaf', '800000.00');

        $enc = Encaissement::firstWhere('reference', 'ENC-SPA-1');

        $this->actingAs($comptable)
            ->postJson(route('api.recettes.encaissements.soumettre', $enc))
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');

        $enc->pieces()->create([
            'nom' => 'j.pdf', 'chemin' => 't/j.pdf', 'type_mime' => 'application/pdf',
            'taille' => 100, 'categorie' => 'justificatif', 'televerse_par' => $comptable->id,
        ]);

        $cle = 'enc-soumettre-1';
        $this->actingAs($comptable)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.recettes.encaissements.soumettre', $enc->fresh()))
            ->assertOk()
            ->assertJsonPath('encaissement.statut', 'en_validation');

        $this->actingAs($comptable)
            ->withHeaders(['Idempotency-Key' => $cle])
            ->postJson(route('api.recettes.encaissements.soumettre', $enc->fresh()))
            ->assertOk()
            ->assertJsonPath('code', 'soumis');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.recettes.encaissements.decisions', $enc->fresh()), ['decision' => 'valider'])
            ->assertOk();
        $this->actingAs($cf)
            ->postJson(route('api.recettes.encaissements.decisions', $enc->fresh()), ['decision' => 'valider'])
            ->assertOk();
        $this->actingAs($comptableVisa)
            ->postJson(route('api.recettes.encaissements.decisions', $enc->fresh()), ['decision' => 'valider'])
            ->assertOk()
            ->assertJsonPath('encaissement.statut', 'valide');

        $this->actingAs($comptableVisa)
            ->postJson(route('api.recettes.encaissements.rapprocher', $enc->fresh()), [
                'date_rapprochement' => '2026-05-15',
                'reference_bancaire' => 'VIR-1',
            ])
            ->assertOk()
            ->assertJsonPath('encaissement.statut', 'rapproche');

        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.recettes.show', $recette->fresh()))
            ->assertOk()
            ->assertJsonPath('data.montant_realise', '800000.00')
            ->assertJsonPath('data.statut', 'partiellement_realisee');
    }
}
