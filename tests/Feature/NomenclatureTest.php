<?php

namespace Tests\Feature;

use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Rapport;
use App\Models\User;
use App\Services\Budget\NomenclatureStatsService;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NomenclatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
        $this->admin = User::create([
            'name' => 'Admin Nom', 'email' => 'admin.nom@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_index_affiche_arbre_et_tableau(): void
    {
        $this->actingAs($this->admin)->get(route('nomenclature.index'))
            ->assertOk()->assertSee('Nomenclature budgétaire')->assertSee('Arborescence');
    }

    public function test_crud_titre_complet(): void
    {
        // Création
        $this->actingAs($this->admin)->post(route('nomenclature.store', 'titre'), [
            'sens' => 'depense', 'code' => 'T-TEST', 'intitule' => 'Titre de test', 'ordre' => 9,
        ])->assertRedirect(route('nomenclature.index'));
        $titre = BudgetTitre::firstWhere('code', 'T-TEST');
        $this->assertNotNull($titre);

        // Unicité (même sens + même code refusé)
        $this->actingAs($this->admin)->post(route('nomenclature.store', 'titre'), [
            'sens' => 'depense', 'code' => 'T-TEST', 'intitule' => 'Doublon',
        ])->assertSessionHasErrors('code');

        // Modification
        $this->actingAs($this->admin)->put(route('nomenclature.update', ['niveau' => 'titre', 'id' => $titre->id]), [
            'sens' => 'depense', 'code' => 'T-TEST', 'intitule' => 'Titre modifié', 'ordre' => 1,
        ])->assertRedirect(route('nomenclature.index'));
        $this->assertSame('Titre modifié', $titre->fresh()->intitule);

        // Archivage (sans enfant) puis restauration
        $this->actingAs($this->admin)->delete(route('nomenclature.archiver', ['niveau' => 'titre', 'id' => $titre->id]));
        $this->assertSoftDeleted('budget_titres', ['id' => $titre->id]);
        $this->actingAs($this->admin)->post(route('nomenclature.restaurer', ['niveau' => 'titre', 'id' => $titre->id]));
        $this->assertDatabaseHas('budget_titres', ['id' => $titre->id, 'deleted_at' => null]);
    }

    public function test_archivage_bloque_si_enfants(): void
    {
        $titre = BudgetTitre::has('articles')->first();
        $this->assertNotNull($titre);

        $this->actingAs($this->admin)->delete(route('nomenclature.archiver', ['niveau' => 'titre', 'id' => $titre->id]))
            ->assertSessionHas('erreur');
        $this->assertDatabaseHas('budget_titres', ['id' => $titre->id, 'deleted_at' => null]);
    }

    public function test_rapport_nomenclature_pdf_excel_csv(): void
    {
        Storage::fake('local');
        $mimes = ['pdf' => 'application/pdf', 'excel' => 'spreadsheetml', 'csv' => 'text/csv'];
        foreach ($mimes as $format => $mime) {
            $resp = $this->actingAs($this->admin)->get(route('reporting.generer', ['type' => 'nomenclature', 'format' => $format]));
            $resp->assertOk();
            $this->assertStringContainsString($mime, $resp->headers->get('content-type'));
        }
        $this->assertSame(3, Rapport::where('type', 'nomenclature')->count());
    }

    public function test_ligne_inactive_non_engageable(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026) ?? Exercice::first();
        $rubrique = BudgetRubrique::first();
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id, 'rubrique_id' => $rubrique->id,
            'code' => 'LB-INACTIVE', 'intitule' => 'Ligne inactive',
            'montant_ouvert' => 1_000_000, 'actif' => false,
        ]);

        $this->assertFalse($ligne->peutEngager(1000), 'Une ligne inactive ne doit pas être engageable');
        $ligne->update(['actif' => true]);
        $this->assertTrue($ligne->fresh()->peutEngager(1000));
    }

    public function test_permission_lecteur(): void
    {
        $lecteur = User::create([
            'name' => 'Lecteur', 'email' => 'lecteur.nom@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        $this->actingAs($lecteur)->get(route('nomenclature.index'))->assertOk();
        $this->actingAs($lecteur)->get(route('nomenclature.create', 'titre'))->assertForbidden();
    }

    // ---- Saisie assistée ligne par ligne (L2) -----------------------------------------------

    public function test_assistant_ecran_accessible(): void
    {
        $this->actingAs($this->admin)->get(route('nomenclature.assistant'))
            ->assertOk()->assertSee('Saisie assistée');

        // Réservé au droit de création
        $lecteur = User::create([
            'name' => 'Lecteur A', 'email' => 'lecteur.assist@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');
        $this->actingAs($lecteur)->get(route('nomenclature.assistant'))->assertForbidden();
    }

    public function test_assistant_suggere_le_prochain_code(): void
    {
        $titre = BudgetTitre::has('articles')->first();
        $article = $titre->articles()->first();
        $existant = $article->code;

        $resp = $this->actingAs($this->admin)->getJson(route('nomenclature.assistant.contexte', [
            'niveau' => 'paragraphe', 'parent_id' => $article->id,
        ]))->assertOk();

        // Le code suggéré prolonge le code parent (ex. 601 → 6011) et n'existe pas déjà.
        $suggere = $resp->json('prochain_code');
        $this->assertNotSame('', $suggere);
        $this->assertStringStartsWith($existant, $suggere);
        $this->assertSame(0, $article->paragraphes()->where('code', $suggere)->count());
    }

    public function test_assistant_verifie_unicite_du_code(): void
    {
        $titre = BudgetTitre::has('articles')->first();
        $article = $titre->articles()->first();

        // Code parent (titre) déjà pris → indisponible pour un nouvel article
        $this->actingAs($this->admin)->getJson(route('nomenclature.assistant.verifier', [
            'niveau' => 'article', 'parent_id' => $titre->id, 'code' => $article->code,
        ]))->assertOk()->assertJson(['disponible' => false]);

        // Code libre → disponible
        $this->actingAs($this->admin)->getJson(route('nomenclature.assistant.verifier', [
            'niveau' => 'article', 'parent_id' => $titre->id, 'code' => 'ZZZ-LIBRE-999',
        ]))->assertOk()->assertJson(['disponible' => true]);
    }

    public function test_assistant_store_json_renvoie_prochain_code(): void
    {
        $titre = BudgetTitre::has('articles')->first();
        $article = $titre->articles()->first();

        $resp = $this->actingAs($this->admin)->postJson(route('nomenclature.store', 'paragraphe'), [
            'article_id' => $article->id, 'code' => $article->code.'9', 'intitule' => 'Paragraphe assisté', 'ordre' => 0,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('budget_paragraphes', [
            'article_id' => $article->id, 'code' => $article->code.'9', 'intitule' => 'Paragraphe assisté',
        ]);
        // Un prochain code est proposé pour enchaîner la saisie suivante.
        $this->assertNotSame('', $resp->json('prochain_code'));
    }

    // ---- Tableau de bord nomenclature (L3) --------------------------------------------------

    public function test_tableau_de_bord_accessible(): void
    {
        $this->actingAs($this->admin)->get(route('nomenclature.tableau'))
            ->assertOk()->assertSee('Tableau de bord')->assertSee('Usage des lignes');
    }

    public function test_stats_usage_distingue_lignes_utilisees_et_inutilisees(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026) ?? Exercice::first();
        $rubrique = BudgetRubrique::first();
        LigneBudgetaire::create([
            'exercice_id' => $exercice->id, 'rubrique_id' => $rubrique->id,
            'code' => 'LB-USED', 'intitule' => 'Engagée', 'montant_ouvert' => 1_000_000, 'montant_engage' => 250_000,
        ]);
        LigneBudgetaire::create([
            'exercice_id' => $exercice->id, 'rubrique_id' => $rubrique->id,
            'code' => 'LB-IDLE', 'intitule' => 'Non engagée', 'montant_ouvert' => 500_000, 'montant_engage' => 0,
        ]);

        $usage = app(NomenclatureStatsService::class)->usageLignes($exercice->id);
        $this->assertSame(2, $usage['total']);
        $this->assertSame(1, $usage['utilisees']);
        $this->assertSame(1, $usage['inutilisees']);

        $codesInutilises = app(NomenclatureStatsService::class)->lignesInutilisees($exercice->id)->pluck('code')->all();
        $this->assertContains('LB-IDLE', $codesInutilises);
        $this->assertNotContains('LB-USED', $codesInutilises);
    }

    // ---- Assistant d'import nomenclature (L4) -----------------------------------------------

    public function test_apercu_import_detecte_lignes_valides_et_rejets(): void
    {
        Storage::fake('local');
        $fichier = $this->csvNomenclature();

        $resp = $this->actingAs($this->admin)
            ->post(route('budget.import.nomenclature.apercu'), ['fichier' => $fichier]);

        $resp->assertOk()->assertSee('Prévisualisation');
        $resp->assertViewHas('valides', 1);   // une ligne complète
        $resp->assertViewHas('total', 2);     // deux lignes lues
        $this->assertNotNull(session('import_nomenclature_path'));
    }

    public function test_confirmation_import_persiste_et_renvoie_rapport(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin)->post(route('budget.import.nomenclature.apercu'), ['fichier' => $this->csvNomenclature()]);

        $this->actingAs($this->admin)->post(route('budget.import.nomenclature.confirmer'))
            ->assertRedirect(route('budget.import.form'))
            ->assertSessionHas('rapport_nomenclature');

        $this->assertDatabaseHas('budget_rubriques', ['code' => '9001', 'intitule' => 'Rubrique 9001']);
        // Le fichier temporaire est nettoyé et la session vidée.
        $this->assertNull(session('import_nomenclature_path'));
    }

    public function test_confirmation_sans_apercu_echoue_proprement(): void
    {
        $this->actingAs($this->admin)->post(route('budget.import.nomenclature.confirmer'))
            ->assertRedirect(route('budget.import.form'))->assertSessionHas('erreur');
    }

    // ---- Workflow de validation + NomenclaturePolicy (L5) -----------------------------------

    public function test_creation_manuelle_naît_brouillon_puis_validation(): void
    {
        $this->actingAs($this->admin)->post(route('nomenclature.store', 'titre'), [
            'sens' => 'depense', 'code' => 'T-WF', 'intitule' => 'Titre workflow',
        ])->assertRedirect(route('nomenclature.index'));

        $titre = BudgetTitre::firstWhere('code', 'T-WF');
        $this->assertSame('brouillon', $titre->statut, 'Une création manuelle doit naître brouillon');

        $this->actingAs($this->admin)->post(route('nomenclature.valider', ['niveau' => 'titre', 'id' => $titre->id]))
            ->assertSessionHas('succes');
        $this->assertSame('valide', $titre->fresh()->statut);
    }

    public function test_separation_des_fonctions_creation_validation(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gestion', 'email' => 'gest.nom@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');
        $directeur = User::create([
            'name' => 'Directeur', 'email' => 'dir.nom@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $directeur->assignRole('directeur');

        // Le gestionnaire crée (brouillon) mais ne peut pas valider.
        $this->actingAs($gestionnaire)->post(route('nomenclature.store', 'titre'), [
            'sens' => 'depense', 'code' => 'T-SOD', 'intitule' => 'Séparation',
        ])->assertRedirect();
        $titre = BudgetTitre::firstWhere('code', 'T-SOD');

        $this->actingAs($gestionnaire)
            ->post(route('nomenclature.valider', ['niveau' => 'titre', 'id' => $titre->id]))
            ->assertForbidden();

        // Le directeur (droit nomenclature.valider) valide.
        $this->actingAs($directeur)
            ->post(route('nomenclature.valider', ['niveau' => 'titre', 'id' => $titre->id]))
            ->assertSessionHas('succes');
        $this->assertSame('valide', $titre->fresh()->statut);
    }

    public function test_import_en_masse_reste_valide(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin)->post(route('budget.import.nomenclature.apercu'), ['fichier' => $this->csvNomenclature()]);
        $this->actingAs($this->admin)->post(route('budget.import.nomenclature.confirmer'));

        // Les imports sont des données de référence : statut « valide » par défaut, pas « brouillon ».
        $this->assertDatabaseHas('budget_rubriques', ['code' => '9001', 'statut' => 'valide']);
    }

    /** Fichier CSV de test : une ligne valide (9001) + une ligne sans rubrique_code (rejetée). */
    private function csvNomenclature(): UploadedFile
    {
        $contenu = "sens,titre_code,titre_intitule,article_code,article_intitule,paragraphe_code,paragraphe_intitule,rubrique_code,rubrique_intitule\n"
            ."depense,9,Titre 9,90,Article 90,900,Paragraphe 900,9001,Rubrique 9001\n"
            ."depense,9,Titre 9,90,Article 90,900,Paragraphe 900,,Sans code\n";
        $chemin = tempnam(sys_get_temp_dir(), 'nom').'.csv';
        file_put_contents($chemin, $contenu);

        return new UploadedFile($chemin, 'nomenclature.csv', 'text/csv', null, true);
    }
}
