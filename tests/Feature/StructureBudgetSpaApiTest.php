<?php

namespace Tests\Feature;

use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructureBudgetSpaApiTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private Structure $direction;

    private LigneBudgetaire $ligne;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, ExerciceSeeder::class, NomenclatureBudgetaireSeeder::class]);

        $this->exercice = Exercice::firstWhere('annee', 2026) ?? Exercice::first();
        $dept = Structure::create([
            'code' => 'SPA-DEPT', 'libelle' => 'Département SPA', 'type' => 'departement',
            'parent_id' => null, 'niveau' => 0, 'ordre' => 1, 'actif' => true,
        ]);
        $this->direction = Structure::create([
            'code' => 'SPA-DIR', 'libelle' => 'Direction SPA', 'type' => 'direction',
            'parent_id' => $dept->id, 'niveau' => 1, 'ordre' => 1, 'actif' => true,
        ]);
        $this->ligne = LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id,
            'structure_id' => $this->direction->id,
            'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'SPA-LB-1',
            'intitule' => 'Ligne SPA',
            'montant_ouvert' => 250000.5,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'actif' => true,
            'statut' => 'actif',
        ])->assignRole($role);
    }

    public function test_montant_api_est_une_chaine_decimale(): void
    {
        $this->actingAs($this->user('lecteur'));

        $this->getJson(route('api.structure-budget.lignes', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('data.0.montant_ouvert', '250000.50')
            ->assertJsonPath('data.0.actions.soumettre', false);
    }

    public function test_lecteur_ne_peut_pas_soumettre(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->postJson(route('api.structure-budget.soumettre', $this->ligne))
            ->assertForbidden();
    }

    public function test_workflow_api_soumettre_valider(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $directeur = $this->user('directeur');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.structure-budget.soumettre', $this->ligne))
            ->assertOk()
            ->assertJsonPath('code', 'soumise')
            ->assertJsonPath('ligne.statut_validation', 'soumis');

        $this->assertSame('soumis', $this->ligne->fresh()->statut_validation);

        $this->actingAs($directeur)
            ->postJson(route('api.structure-budget.valider', $this->ligne))
            ->assertOk()
            ->assertJsonPath('ligne.statut_validation', 'valide');
    }

    public function test_rejet_exige_un_motif(): void
    {
        $this->actingAs($this->user('gestionnaire_budgetaire'))
            ->postJson(route('api.structure-budget.soumettre', $this->ligne))
            ->assertOk();

        $this->actingAs($this->user('directeur'))
            ->postJson(route('api.structure-budget.rejeter', $this->ligne), [])
            ->assertStatus(422);
    }

    public function test_idempotence_de_la_soumission(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $headers = ['Idempotency-Key' => 'cle-test-soumettre-1'];

        $this->actingAs($gestionnaire)
            ->postJson(route('api.structure-budget.soumettre', $this->ligne), [], $headers)
            ->assertOk()
            ->assertJsonPath('ligne.statut_validation', 'soumis');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.structure-budget.soumettre', $this->ligne), [], $headers)
            ->assertOk()
            ->assertJsonPath('code', 'soumise');

        $this->assertSame('soumis', $this->ligne->fresh()->statut_validation);
    }

    public function test_seconde_soumission_sans_cle_est_refusee(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.structure-budget.soumettre', $this->ligne))
            ->assertOk();

        $this->actingAs($gestionnaire)
            ->postJson(route('api.structure-budget.soumettre', $this->ligne))
            ->assertStatus(422)
            ->assertJsonPath('code', 'transition_invalide');
    }

    public function test_if_match_refuse_une_version_obsolete(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)
            ->withHeaders(['If-Match' => '2000-01-01T00:00:00+00:00'])
            ->postJson(route('api.structure-budget.soumettre', $this->ligne))
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');
    }

    public function test_jeton_tompro_ne_peut_pas_lire_les_lignes(): void
    {
        Sanctum::actingAs($this->user('lecteur'), ['tompro']);

        $this->getJson(route('api.structure-budget.lignes', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }

    public function test_hub_et_import_csv_sans_ieee(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->getJson(route('api.structure-budget.import-export', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertJsonPath('synthese.ouvert', '250000.50')
            ->assertJsonPath('peut_importer', false);

        $this->actingAs($this->user('lecteur'))
            ->post(route('api.structure-budget.import.apercu'), [
                'exercice_id' => $this->exercice->id,
            ])
            ->assertForbidden();

        $rub = BudgetRubrique::with('paragraphe.article.titre')->first();
        $this->assertNotNull($rub);
        $csv = implode("\n", [
            'code,intitule,structure_code,rubrique_code,paragraphe_code,article_code,titre_code,montant_ouvert',
            sprintf('SB-SPA-1,Import SPA,%s,%s,%s,%s,%s,125000.25',
                $this->direction->code,
                $rub->code,
                $rub->paragraphe->code,
                $rub->paragraphe->article->code,
                $rub->paragraphe->article->titre->code,
            ),
        ]);
        $file = UploadedFile::fake()->createWithContent('structure.csv', $csv);
        $admin = $this->user('administrateur');

        $this->actingAs($admin)
            ->post(route('api.structure-budget.import.apercu'), [
                'exercice_id' => $this->exercice->id,
                'fichier' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('valides', 1)
            ->assertJsonPath('peut_confirmer', true)
            ->assertJsonPath('apercu.0.montant_ouvert', '125000.25');

        $this->actingAs($admin)
            ->postJson(route('api.structure-budget.import.confirmer'))
            ->assertOk()
            ->assertJsonPath('data.importees', 1);

        $this->assertDatabaseHas('lignes_budgetaires', [
            'exercice_id' => $this->exercice->id,
            'code' => 'SB-SPA-1',
        ]);
    }
}
