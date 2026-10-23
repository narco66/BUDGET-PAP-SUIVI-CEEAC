<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Structure;
use App\Models\User;
use App\Services\Budget\StructureBudgetService;
use App\Support\AuditLogger;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructureBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Exercice $exercice;
    private Structure $dept;
    private Structure $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]); // bypass du middleware MFA (cf. AdministrationTechniqueTest)
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin SB', 'email' => 'admin.sb@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');

        $this->exercice = Exercice::firstWhere('annee', 2026) ?? Exercice::first();
        $this->dept = Structure::create([
            'code' => 'SB-DEPT', 'libelle' => 'Département test', 'type' => 'departement',
            'parent_id' => null, 'niveau' => 0, 'ordre' => 1, 'actif' => true,
        ]);
        $this->dir = Structure::create([
            'code' => 'SB-DIR', 'libelle' => 'Direction test', 'type' => 'direction',
            'parent_id' => $this->dept->id, 'niveau' => 1, 'ordre' => 1, 'actif' => true,
        ]);
    }

    private function ligne(int $structureId, string $code, float $ouvert, float $engage = 0): LigneBudgetaire
    {
        return LigneBudgetaire::create([
            'exercice_id' => $this->exercice->id, 'structure_id' => $structureId,
            'rubrique_id' => BudgetRubrique::first()->id, 'code' => $code,
            'montant_ouvert' => $ouvert, 'montant_engage' => $engage,
        ]);
    }

    private function trouver(array $noeuds, string $code): ?array
    {
        foreach ($noeuds as $n) {
            if ($n['structure']->code === $code) {
                return $n;
            }
            if ($f = $this->trouver($n['enfants'], $code)) {
                return $f;
            }
        }

        return null;
    }

    public function test_arbre_consolide_les_montants_en_remontant(): void
    {
        $this->ligne($this->dir->id, 'SB-1', 1_000_000, 400_000);
        $this->ligne($this->dir->id, 'SB-2', 500_000, 100_000);

        $arbre = app(StructureBudgetService::class)->arbre($this->exercice->id);
        $dept = $this->trouver($arbre, 'SB-DEPT');
        $dir = $this->trouver($arbre, 'SB-DIR');

        // Le département (sans ligne propre) cumule le sous-arbre de sa direction.
        $this->assertNotNull($dept);
        $this->assertSame(0, $dept['nb_propre']);
        $this->assertSame(2, $dept['nb_total']);
        $this->assertEqualsWithDelta(1_500_000, $dept['total_ouvert'], 0.01);
        $this->assertEqualsWithDelta(500_000, $dept['total_engage'], 0.01);

        // La direction porte ses 2 lignes propres.
        $this->assertSame(2, $dir['nb_propre']);
        $this->assertEqualsWithDelta(1_500_000, $dir['total_ouvert'], 0.01);
    }

    public function test_totaux_par_type_direction(): void
    {
        $this->ligne($this->dir->id, 'SB-1', 1_500_000);

        $directions = app(StructureBudgetService::class)->totauxParType($this->exercice->id, 'direction');
        $ligne = $directions->firstWhere('code', 'SB-DIR');

        $this->assertNotNull($ligne);
        $this->assertEqualsWithDelta(1_500_000, $ligne['ouvert'], 0.01);
    }

    public function test_arbre_et_tableau_accessibles(): void
    {
        $this->actingAs($this->admin)->get(route('structure-budget.arbre', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Structure du budget');
        $this->actingAs($this->admin)->get(route('structure-budget.tableau', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Tableau de bord');
    }

    public function test_wizard_affiche_les_etapes_et_la_synthese(): void
    {
        $resp = $this->actingAs($this->admin)->get(route('structure-budget.wizard', ['exercice' => $this->exercice->id]))->assertOk();
        // Les 6 étapes du parcours métier.
        foreach (['Exercice', 'Structure', 'Activité PAP', 'Nomenclature', 'Financement', 'Validation'] as $etape) {
            $resp->assertSee($etape, false);
        }
        // Bloc de synthèse budgétaire (Phase 7/8) + nonce CSP sur le script inline.
        $resp->assertSee('synthese-pap', false)->assertSee('Synthèse budgétaire', false);
    }

    public function test_wizard_cree_une_ligne(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gest SB', 'email' => 'gest.sb@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)->get(route('structure-budget.wizard', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('wizard');

        $this->actingAs($gestionnaire)->post(route('structure-budget.wizard.store'), [
            'exercice_id' => $this->exercice->id, 'structure_id' => $this->dir->id,
            'rubrique_id' => BudgetRubrique::first()->id, 'code' => 'SB-NEW-1',
            'intitule' => 'Ligne wizard', 'montant_ouvert' => 750_000,
        ])->assertRedirect();

        $this->assertDatabaseHas('lignes_budgetaires', [
            'exercice_id' => $this->exercice->id, 'structure_id' => $this->dir->id, 'code' => 'SB-NEW-1',
        ]);
    }

    public function test_assistant_redirige_vers_wizard(): void
    {
        $this->actingAs($this->admin)->get(route('structure-budget.assistant', ['exercice' => $this->exercice->id]))
            ->assertRedirect(route('structure-budget.wizard', ['exercice' => $this->exercice->id]));
    }

    public function test_wizard_verifier_detecte_code_duplique(): void
    {
        $this->ligne($this->dir->id, 'SB-DUP', 100_000);

        $result = app(\App\Services\Budget\StructureBudgetWizardService::class)->verifier($this->exercice->id, [
            'structure_id' => $this->dir->id,
            'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'SB-DUP',
            'montant_ouvert' => 50_000,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['erreurs']);
    }

    public function test_wizard_contexte_et_activites_json(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-W', 'intitule' => 'Pilier W', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-W', 'intitule' => 'Axe W']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-W', 'intitule' => 'Produit W']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP-W', 'intitule' => 'SP W']);
        Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'structure_id' => $this->dir->id,
            'code' => 'ACT-W', 'intitule' => 'Activité W', 'montant_total' => 1_000_000,
        ]);

        $this->actingAs($this->admin)->getJson(route('structure-budget.wizard.contexte', [
            'exercice_id' => $this->exercice->id, 'structure_id' => $this->dir->id,
        ]))->assertOk()->assertJsonPath('exercice.id', $this->exercice->id)
            ->assertJsonStructure(['code_suggere', 'structure' => ['fil']]);

        $this->actingAs($this->admin)->getJson(route('structure-budget.wizard.activites', [
            'exercice_id' => $this->exercice->id, 'structure_id' => $this->dir->id,
        ]))->assertOk()->assertJsonFragment(['code' => 'ACT-W']);
    }

    public function test_wizard_alerte_depassement_montant_pap(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-A', 'intitule' => 'P', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'A']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'structure_id' => $this->dir->id,
            'code' => 'ACT-A', 'intitule' => 'A', 'montant_total' => 500_000,
        ]);

        $result = app(\App\Services\Budget\StructureBudgetWizardService::class)->verifier($this->exercice->id, [
            'structure_id' => $this->dir->id,
            'activite_id' => $activite->id,
            'rubrique_id' => BudgetRubrique::first()->id,
            'code' => 'SB-ALERT',
            'montant_ouvert' => 600_000,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['alertes']);
    }

    public function test_arbre_complet_accessible_avec_chaine_pap(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-SB', 'intitule' => 'Pilier SB', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-SB', 'intitule' => 'Axe SB']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-SB', 'intitule' => 'Produit SB']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP-SB', 'intitule' => 'SP SB']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'structure_id' => $this->dir->id,
            'code' => 'ACT-SB', 'intitule' => 'Activité SB', 'montant_total' => 0,
        ]);
        $ligne = $this->ligne($this->dir->id, 'SB-PAP', 800_000);
        $ligne->update(['activite_id' => $activite->id]);

        $donnees = app(StructureBudgetService::class)->arbreComplet($this->exercice->id);

        $this->assertNotEmpty($donnees['institutionnel']);
        $this->assertNotEmpty($donnees['programmatique']);
        $this->assertSame('P-SB', $donnees['programmatique'][0]['code']);

        $this->actingAs($this->admin)->get(route('structure-budget.arbre-complet', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Arbre complet')->assertSee('P-SB');
    }

    public function test_recherche_par_code(): void
    {
        $this->ligne($this->dir->id, 'SB-FIND-ME', 200_000);

        $resultats = app(StructureBudgetService::class)->rechercher($this->exercice->id, ['q' => 'FIND-ME']);

        $this->assertSame(1, $resultats->total());
        $this->assertSame('SB-FIND-ME', $resultats->first()->code);

        $this->actingAs($this->admin)->get(route('structure-budget.recherche', [
            'exercice' => $this->exercice->id, 'q' => 'FIND-ME',
        ]))->assertOk()->assertSee('SB-FIND-ME');
    }

    public function test_fiche_ligne_avec_contexte(): void
    {
        $ligne = $this->ligne($this->dir->id, 'SB-SHOW', 300_000);

        $this->actingAs($this->admin)->get(route('structure-budget.ligne.show', [
            'ligne' => $ligne, 'exercice' => $this->exercice->id,
        ]))->assertOk()->assertSee('SB-SHOW')->assertSee('Nomenclature');
    }

    public function test_gestionnaire_peut_modifier_ligne_via_module(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gest Mod', 'email' => 'gest.mod@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');
        $ligne = $this->ligne($this->dir->id, 'SB-EDIT', 400_000);

        $this->actingAs($gestionnaire)->put(route('structure-budget.ligne.update', $ligne), [
            'exercice_id' => $this->exercice->id, 'structure_id' => $this->dir->id,
            'rubrique_id' => BudgetRubrique::first()->id, 'code' => 'SB-EDIT',
            'intitule' => 'Modifiée', 'montant_ouvert' => 450_000,
        ])->assertRedirect();

        $this->assertDatabaseHas('lignes_budgetaires', ['code' => 'SB-EDIT', 'intitule' => 'Modifiée']);
    }

    public function test_suppression_refusee_si_engagee(): void
    {
        $ligne = $this->ligne($this->dir->id, 'SB-ENG', 500_000, 100_000);
        $this->assertFalse($ligne->peutEtreSupprimee());

        $this->actingAs($this->admin)->delete(route('structure-budget.ligne.destroy', $ligne))
            ->assertRedirect()->assertSessionHas('erreur');

        $this->assertDatabaseHas('lignes_budgetaires', ['code' => 'SB-ENG', 'deleted_at' => null]);
    }

    public function test_export_excel_accessible_au_gestionnaire(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gest Exp', 'email' => 'gest.exp@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');
        $this->ligne($this->dir->id, 'SB-EXP', 100_000);

        $this->actingAs($gestionnaire)->get(route('structure-budget.export', ['exercice' => $this->exercice->id]))
            ->assertOk();
    }

    public function test_lecteur_ne_peut_pas_exporter(): void
    {
        $lecteur = User::create([
            'name' => 'Lect Exp', 'email' => 'lect.exp@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        $this->actingAs($lecteur)->get(route('structure-budget.export', ['exercice' => $this->exercice->id]))
            ->assertForbidden();
    }

    public function test_assistant_refuse_activite_incoherente(): void
    {
        $autre = Structure::create([
            'code' => 'SB-OTHER', 'libelle' => 'Autre', 'type' => 'service',
            'parent_id' => $this->dir->id, 'niveau' => 2, 'ordre' => 1, 'actif' => true,
        ]);
        // Chaîne RBM construite manuellement, épinglée à l'exercice seedé (évite la collision
        // d'année que provoquerait Activite::factory en créant son propre Exercice aléatoire).
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-'.uniqid(), 'intitule' => 'Pilier']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'SP']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'structure_id' => $autre->id,
            'code' => 'ACT-'.uniqid(), 'intitule' => 'Activité autre', 'montant_total' => 0,
        ]);

        $this->actingAs($this->admin)->post(route('structure-budget.wizard.store'), [
            'exercice_id' => $this->exercice->id, 'structure_id' => $this->dir->id,
            'rubrique_id' => BudgetRubrique::first()->id, 'activite_id' => $activite->id,
            'code' => 'SB-KO', 'montant_ouvert' => 100_000,
        ])->assertSessionHasErrors('activite_id');

        $this->assertDatabaseMissing('lignes_budgetaires', ['code' => 'SB-KO']);
    }

    public function test_permissions_structure_budget(): void
    {
        $lecteur = User::create([
            'name' => 'Lecteur SB', 'email' => 'lecteur.sb@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $lecteur->assignRole('lecteur');

        // Lecture autorisée (structure_budget.voir via *.voir), saisie refusée (structure_budget.creer requis).
        $this->actingAs($lecteur)->get(route('structure-budget.arbre'))->assertOk();
        $this->actingAs($lecteur)->get(route('structure-budget.arbre-complet'))->assertOk();
        $this->actingAs($lecteur)->get(route('structure-budget.recherche'))->assertOk();
        $this->actingAs($lecteur)->get(route('structure-budget.wizard'))->assertForbidden();
    }

    public function test_structure_a_relations_lignes_et_activites(): void
    {
        $ligne = $this->ligne($this->dir->id, 'SB-REL', 50_000);

        $this->assertTrue($this->dir->lignesBudgetaires()->where('id', $ligne->id)->exists());
    }

    public function test_hub_import_export_accessible(): void
    {
        $this->actingAs($this->admin)->get(route('structure-budget.import-export', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Import / Export');
    }

    public function test_export_csv_et_pdf(): void
    {
        $this->ligne($this->dir->id, 'SB-EXP2', 100_000);

        $this->actingAs($this->admin)->get(route('structure-budget.export', [
            'exercice' => $this->exercice->id, 'format' => 'csv',
        ]))->assertOk();

        $this->actingAs($this->admin)->get(route('structure-budget.export', [
            'exercice' => $this->exercice->id, 'format' => 'pdf',
        ]))->assertOk();
    }

    public function test_modele_import_structure_budget(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gest Imp', 'email' => 'gest.imp@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)->get(route('structure-budget.import.modele'))->assertOk();
    }

    public function test_import_previsualisation_et_confirmation(): void
    {
        $rub = BudgetRubrique::with('paragraphe.article.titre')->first();
        $this->assertNotNull($rub);

        $csv = implode("\n", [
            'code,intitule,structure_code,rubrique_code,paragraphe_code,article_code,titre_code,montant_ouvert',
            sprintf('SB-CSV-1,Import test,SB-DIR,%s,%s,%s,%s,250000',
                $rub->code,
                $rub->paragraphe->code,
                $rub->paragraphe->article->code,
                $rub->paragraphe->article->titre->code,
            ),
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('structure.csv', $csv);

        $this->actingAs($this->admin)->post(route('structure-budget.import.apercu'), [
            'exercice_id' => $this->exercice->id,
            'fichier' => $file,
        ])->assertOk()->assertSee('Prévisualisation')->assertSee('SB-CSV-1');

        $this->actingAs($this->admin)->post(route('structure-budget.import.confirmer'))
            ->assertRedirect(route('structure-budget.import-export', ['exercice' => $this->exercice->id]))
            ->assertSessionHas('succes');

        $this->assertDatabaseHas('lignes_budgetaires', [
            'exercice_id' => $this->exercice->id, 'code' => 'SB-CSV-1',
        ]);
    }

    public function test_import_rejette_ligne_invalide(): void
    {
        $csv = "code,intitule,structure_code,rubrique_code,montant_ouvert\nSB-KO,Test,SB-DIR,INEXISTANT,100\n";

        $motif = \App\Imports\StructureBudgetLignesImport::motifInvalidite([
            'code' => 'SB-KO', 'rubrique_code' => 'INEXISTANT', 'structure_code' => 'SB-DIR', 'montant_ouvert' => 100,
        ], $this->exercice->id);

        $this->assertNotNull($motif);
    }

    public function test_observer_synchronise_ligne_vers_activite(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-OBS', 'intitule' => 'P', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-OBS', 'intitule' => 'A']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-OBS', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP-OBS', 'intitule' => 'SP']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'structure_id' => $this->dir->id,
            'code' => 'ACT-OBS', 'intitule' => 'Obs', 'montant_total' => 100_000,
        ]);
        $ligne = $this->ligne($this->dir->id, 'SB-OBS', 100_000);
        $ligne->update(['activite_id' => $activite->id]);

        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);
    }

    public function test_wizard_rejette_rubrique_brouillon(): void
    {
        $rub = BudgetRubrique::first();
        $rub->update(['statut' => 'brouillon']);

        $result = app(\App\Services\Budget\StructureBudgetWizardService::class)->verifier($this->exercice->id, [
            'structure_id' => $this->dir->id,
            'rubrique_id' => $rub->id,
            'code' => 'SB-BROU',
            'montant_ouvert' => 50_000,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['erreurs']);
    }

    public function test_coherence_service_evolution_et_anomalies(): void
    {
        Exercice::create([
            'annee' => 2025, 'code' => '2025', 'libelle' => 'Exercice 2025',
            'statut' => 'cloture', 'est_actif' => false,
            'date_debut' => '2025-01-01', 'date_fin' => '2025-12-31', 'devise' => 'XAF',
        ]);
        $this->ligne($this->dir->id, 'SB-N', 500_000);

        $coherence = app(\App\Services\Budget\StructureBudgetCoherenceService::class);
        $evolution = $coherence->evolution($this->exercice->id);

        $this->assertNotNull($evolution);
        $this->assertSame(2026, $evolution['annee_courante']);
        $this->assertSame(2025, $evolution['annee_precedente']);
        $this->assertSame(500_000.0, $evolution['ouvert_courant']);

        $repartition = $coherence->repartitionParSens($this->exercice->id);
        $this->assertTrue($repartition->isNotEmpty());
    }

    public function test_tableau_bord_affiche_coherence_lot5(): void
    {
        Exercice::create([
            'annee' => 2025, 'code' => '2025', 'libelle' => 'Exercice 2025',
            'statut' => 'cloture', 'est_actif' => false,
            'date_debut' => '2025-01-01', 'date_fin' => '2025-12-31', 'devise' => 'XAF',
        ]);
        $this->ligne($this->dir->id, 'SB-TDB', 300_000);

        $this->actingAs($this->admin)->get(route('structure-budget.tableau', ['exercice' => $this->exercice->id]))
            ->assertOk()
            ->assertSee('Évolution budgétaire')
            ->assertSee('Répartition par sens budgétaire');
    }

    public function test_reconciliation_liaisons_activite_ligne(): void
    {
        $pilier = Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-REC', 'intitule' => 'P', 'ordre' => 1]);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A-REC', 'intitule' => 'A']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR-REC', 'intitule' => 'PR']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP-REC', 'intitule' => 'SP']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id, 'produit_id' => $produit->id, 'structure_id' => $this->dir->id,
            'code' => 'ACT-REC', 'intitule' => 'Rec', 'montant_total' => 0,
        ]);
        $ligne = $this->ligne($this->dir->id, 'SB-REC', 200_000);
        $ligne->update(['activite_id' => $activite->id]);
        \Illuminate\Support\Facades\DB::table('lignes_budgetaires')->where('id', $ligne->id)->update(['activite_id' => null]);
        $this->assertNull($ligne->fresh()->activite_id);
        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);

        $this->actingAs($this->admin)->post(route('structure-budget.reconcilier', ['exercice' => $this->exercice->id]))
            ->assertRedirect(route('structure-budget.tableau', ['exercice' => $this->exercice->id]))
            ->assertSessionHas('succes');

        $this->assertSame($activite->id, $ligne->fresh()->activite_id);
    }

    public function test_workflow_soumettre_valider_ligne(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gest WF', 'email' => 'gest.wf@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');

        $directeur = User::create([
            'name' => 'Dir WF', 'email' => 'dir.wf@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $directeur->assignRole('directeur');

        $ligne = $this->ligne($this->dir->id, 'SB-WF', 100_000);
        $this->assertSame('brouillon', $ligne->statut_validation);

        $this->actingAs($gestionnaire)->post(route('structure-budget.ligne.soumettre', $ligne))
            ->assertRedirect()->assertSessionHas('succes');
        $this->assertSame('soumis', $ligne->fresh()->statut_validation);

        $this->actingAs($directeur)->post(route('structure-budget.ligne.valider', $ligne))
            ->assertRedirect()->assertSessionHas('succes');
        $this->assertSame('valide', $ligne->fresh()->statut_validation);
        $this->assertNotNull($ligne->fresh()->valide_le);
    }

    public function test_ligne_validee_non_modifiable(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gest Lock', 'email' => 'gest.lock@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');

        $ligne = $this->ligne($this->dir->id, 'SB-LOCK', 100_000);
        $ligne->update(['statut_validation' => 'valide']);

        $this->actingAs($gestionnaire)->put(route('structure-budget.ligne.update', $ligne), [
            'exercice_id' => $this->exercice->id, 'structure_id' => $this->dir->id,
            'rubrique_id' => BudgetRubrique::first()->id, 'code' => 'SB-LOCK',
            'intitule' => 'Bloquée', 'montant_ouvert' => 150_000,
        ])->assertForbidden();
    }

    public function test_rejet_avec_motif(): void
    {
        $gestionnaire = User::create([
            'name' => 'Gest Rej', 'email' => 'gest.rej@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $gestionnaire->assignRole('gestionnaire_budgetaire');
        $directeur = User::create([
            'name' => 'Dir Rej', 'email' => 'dir.rej@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $directeur->assignRole('directeur');

        $ligne = $this->ligne($this->dir->id, 'SB-REJ', 50_000);
        $this->actingAs($gestionnaire)->post(route('structure-budget.ligne.soumettre', $ligne));
        $this->actingAs($directeur)->post(route('structure-budget.ligne.rejeter', $ligne), [
            'motif_rejet' => 'Montant incohérent avec le PAP',
        ])->assertRedirect()->assertSessionHas('succes');

        $ligne->refresh();
        $this->assertSame('rejete', $ligne->statut_validation);
        $this->assertSame('Montant incohérent avec le PAP', $ligne->motif_rejet);
    }

    public function test_journal_structure_budget_accessible(): void
    {
        $ligne = $this->ligne($this->dir->id, 'SB-JRN', 10_000);
        AuditLogger::enregistrer('structure_budget_ligne_creee', $ligne, null, ['code' => $ligne->code]);

        $this->actingAs($this->admin)->get(route('structure-budget.journal', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertSee('Journal')->assertSee('SB-JRN');
    }

    public function test_api_structure_budget_synthese_et_lignes(): void
    {
        $this->ligne($this->dir->id, 'SB-API', 200_000);
        $user = User::create([
            'name' => 'API SB', 'email' => 'api.sb@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $user->assignRole('lecteur');
        Sanctum::actingAs($user, ['lecture']);

        $this->getJson(route('api.structure-budget.synthese', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertJsonStructure(['data' => ['exercice_id', 'synthese']]);

        $this->getJson(route('api.structure-budget.lignes', ['exercice' => $this->exercice->id]))
            ->assertOk()->assertJsonFragment(['code' => 'SB-API']);
    }
}
