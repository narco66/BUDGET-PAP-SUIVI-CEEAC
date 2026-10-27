<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\ImportLot;
use App\Models\Indicateur;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportLotService;
use App\Services\Workflow\DecisionMetierService;
use App\Services\Workflow\TacheQueryService;
use Database\Seeders\ImportPermissionsSeeder;
use Database\Seeders\ImportWorkflowSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Module Import : cycle contrôlé, protection des données, approbation, idempotence, sécurité des fichiers. */
class ImportModuleTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    private ImportLotService $lots;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.enabled' => false]);
        Storage::fake('local');
        $this->seed([RoleSeeder::class, ImportPermissionsSeeder::class, ImportWorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2027, 'libelle' => 'Ex 2027', 'statut' => 'ouvert']);
        $this->lots = app(ImportLotService::class);
    }

    /** @param list<string> $permissions */
    private function utilisateur(array $permissions = [], ?string $role = null): User
    {
        $u = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        if ($role) {
            $u->assignRole($role);
        }
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->givePermissionTo($permissions);

        return $u->fresh();
    }

    private function preparateur(array $metier): User
    {
        return $this->utilisateur(array_merge(['import.view', 'import.create', 'import.upload', 'import.map', 'import.validate', 'import.simulate', 'import.execute', 'import.cancel', 'import.download_report'], $metier));
    }

    private function csv(string $contenu, string $nom = 'donnees.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, $contenu);
    }

    /** @param list<list<mixed>> $lignes */
    private function xlsx(array $lignes): UploadedFile
    {
        $classeur = new Spreadsheet();
        $classeur->getActiveSheet()->fromArray($lignes);
        $chemin = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        return new UploadedFile($chemin, 'donnees.xlsx', null, null, true);
    }

    private function lot(User $user, string $domaine, UploadedFile $fichier, string $mode = 'insertion', bool $exercice = false): ImportLot
    {
        $this->actingAs($user);
        $lot = $this->lots->creer($domaine, $mode, $exercice ? $this->exercice->id : null, $user);

        return $this->lots->deposer($lot, $fichier);
    }

    private function nomenclatureCsv(): string
    {
        return "sens;titre_code;titre_intitule;article_code;article_intitule;paragraphe_code;paragraphe_intitule;rubrique_code;rubrique_intitule\n"
            ."depense;6;Charges;61;Services extérieurs;614;Entretien;614300;Maintenance et réparation\n"
            ."depense;6;Charges;61;Services extérieurs;614;Entretien;614400;Entretien des véhicules\n";
    }

    private function approbateurBudget(): User
    {
        return $this->utilisateur([], 'directeur');
    }

    // ── Nomenclature : cycle complet avec approbation ─────────────────────────────────────────

    public function test_nomenclature_analysee_simulee_approuvee_puis_executee(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $lot = $this->lot($preparateur, 'nomenclature', $this->csv($this->nomenclatureCsv()));

        $this->assertSame('simule', $lot->statut);
        $this->assertSame(2, $lot->total('inserer'));
        $this->assertSame(0, BudgetRubrique::count(), 'La simulation n\'écrit aucune donnée métier.');

        // Lot sensible : exécution impossible avant approbation.
        $this->expectExceptionOnce(fn () => app(ImportExecutionService::class)->lancer($lot, $preparateur), 'approbation');

        $approbateur = $this->approbateurBudget();
        $this->lots->soumettre($lot, $preparateur);
        $lot->refresh();
        $this->assertSame('en_attente_approbation', $lot->statut);
        $tache = WorkflowTache::query()->where('objet_type', $lot->getMorphClass())->where('objet_id', $lot->id)->ouvertes()->first();
        $this->assertNotNull($tache, 'Une tâche d\'approbation est créée.');
        $this->assertSame('IMPORT', $tache->module);
        $this->assertTrue(app(TacheQueryService::class)->collection($approbateur, 'mes')->contains('id', $tache->id), 'Elle figure dans « Mes Tâches » de l\'approbateur.');
        $this->assertTrue(DB::table('notifications')->where('notifiable_id', $approbateur->id)->where('action_statut', 'en_attente')->exists(), 'L\'approbateur est notifié.');
        $this->assertFalse(app(\App\Services\WorkflowService::class)->peutAgir($lot->workflowActif, $preparateur), 'Le préparateur ne peut pas approuver son propre lot.');

        $this->actingAs($approbateur);
        app(DecisionMetierService::class)->decider($lot, $approbateur, ['decision' => 'valider']);
        $this->assertSame('approuve', $lot->fresh()->statut);

        $this->actingAs($preparateur);
        $lot = app(ImportExecutionService::class)->lancer($lot->fresh(), $preparateur);
        $this->assertSame('termine', $lot->statut);
        $this->assertSame(2, $lot->total('executees'));
        $this->assertSame(2, BudgetRubrique::count());
        $this->assertSame(1, \App\Models\BudgetParagraphe::count(), 'Les parents communs ne sont créés qu\'une fois.');
    }

    public function test_reimport_du_meme_fichier_ne_cree_aucun_doublon(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $premier = $this->lot($preparateur, 'nomenclature', $this->csv($this->nomenclatureCsv()));
        $premier->update(['statut' => 'approuve']);
        app(ImportExecutionService::class)->lancer($premier->fresh(), $preparateur);

        $second = $this->lot($preparateur, 'nomenclature', $this->csv($this->nomenclatureCsv()));

        $this->assertSame(2, $second->total('identique'));
        $this->assertSame(0, $second->total('a_ecrire'));
        $this->assertTrue($second->anomalies()->where('regle', 'fichier_deja_importe')->exists());
        $this->expectExceptionOnce(fn () => $this->lots->soumettre($second, $preparateur), 'Aucune ligne');
        $this->assertSame(2, BudgetRubrique::count());
    }

    public function test_colonne_obligatoire_manquante_puis_correspondance_manuelle(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $csv = "sens;T;titre_intitule;article_code;article_intitule;paragraphe_code;paragraphe_intitule;rubrique_code;rubrique_intitule\n"
            ."depense;6;Charges;61;Services;614;Entretien;614300;Maintenance\n";
        $lot = $this->lot($preparateur, 'nomenclature', $this->csv($csv));
        $this->assertSame('mapping', $lot->statut, 'Colonne « titre_code » non reconnue : correspondance à compléter.');

        $mapping = $lot->mapping;
        $mapping['titre_code'] = 'T';
        $lot = $this->lots->definirMapping($lot, $mapping, $preparateur, 'Modèle T');
        $this->assertSame('simule', $lot->statut);
        $this->assertDatabaseHas('import_mappings', ['domaine' => 'nomenclature', 'nom' => 'Modèle T']);
    }

    public function test_detection_automatique_des_colonnes_par_synonymes_et_lecture_excel(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $lot = $this->lot($preparateur, 'nomenclature', $this->xlsx([
            ['Sens', 'Titre', 'Article', 'Paragraphe', 'Code imputation', 'Libellé rubrique'],
            ['depense', '6', '61', '614', '614300', 'Maintenance'],
        ]));

        $this->assertSame('simule', $lot->statut);
        $this->assertSame('Code imputation', $lot->mapping['rubrique_code']);
        $this->assertSame(1, $lot->total('inserer'));
    }

    public function test_lecture_json(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $json = json_encode([['titre_code' => '6', 'article_code' => '61', 'paragraphe_code' => '614', 'rubrique_code' => '614300', 'rubrique_intitule' => 'Maintenance']]);
        $lot = $this->lot($preparateur, 'nomenclature', $this->csv($json, 'donnees.json'));

        $this->assertSame(1, $lot->total('inserer'));
    }

    // ── Budget annuel : protection des crédits officiels ──────────────────────────────────────

    private function nomenclatureExistante(): BudgetRubrique
    {
        $titre = \App\Models\BudgetTitre::create(['sens' => 'depense', 'code' => '6', 'intitule' => 'Charges']);
        $article = \App\Models\BudgetArticle::create(['titre_id' => $titre->id, 'code' => '61', 'intitule' => 'Services']);
        $paragraphe = \App\Models\BudgetParagraphe::create(['article_id' => $article->id, 'code' => '614', 'intitule' => 'Entretien']);

        return BudgetRubrique::create(['paragraphe_id' => $paragraphe->id, 'code' => '614300', 'intitule' => 'Maintenance']);
    }

    private function lignesCsv(string ...$lignes): string
    {
        return "code;intitule;rubrique_code;paragraphe_code;structure_code;montant_ouvert\n".implode("\n", $lignes)."\n";
    }

    public function test_lignes_budgetaires_controles_formats_doublons_et_references(): void
    {
        $this->nomenclatureExistante();
        Structure::create(['code' => 'DIR-BUDGET', 'libelle' => 'Direction du Budget', 'type' => 'direction', 'actif' => true]);
        $preparateur = $this->preparateur(['ligne_budgetaire.creer']);
        $lot = $this->lot($preparateur, 'lignes_budgetaires', $this->csv($this->lignesCsv(
            'LB-1;Maintenance;614300;614;DIR-BUDGET;1 500 000',
            'LB-2;Montant faux;614300;614;DIR-BUDGET;abc',
            'LB-1;Doublon;614300;614;DIR-BUDGET;10',
            'LB-3;Structure inconnue;614300;614;DIR-XXX;10',
            'LB-4;Rubrique inconnue;999999;999;DIR-BUDGET;10',
            'LB-5;Devise;614300;614;DIR-BUDGET;1000 EUR',
        )), 'insertion', true);

        $this->assertSame('anomalies_detectees', $lot->statut);
        $regles = $lot->anomalies()->pluck('regle')->all();
        foreach (['format_montant', 'doublon_fichier', 'reference_inexistante'] as $regle) {
            $this->assertContains($regle, $regles);
        }
        $this->assertSame('1500000.00', $lot->lignes()->where('numero', 2)->first()->donnees['montant_ouvert'], 'Montant normalisé sans conversion.');
        $this->assertSame(0, LigneBudgetaire::count());
        $this->expectExceptionOnce(fn () => app(ImportExecutionService::class)->lancer($lot, $preparateur), 'exécutable');
    }

    public function test_un_credit_ouvert_existant_n_est_jamais_reecrit(): void
    {
        $rubrique = $this->nomenclatureExistante();
        LigneBudgetaire::create(['exercice_id' => $this->exercice->id, 'code' => 'LB-1', 'intitule' => 'Maintenance', 'rubrique_id' => $rubrique->id, 'montant_ouvert' => 7_500_000]);
        $preparateur = $this->preparateur(['ligne_budgetaire.creer']);
        $lot = $this->lot($preparateur, 'lignes_budgetaires', $this->csv($this->lignesCsv(
            'LB-1;Maintenance;614300;614;;9 999 999',
            'LB-2;Nouvelle ligne;614300;614;;500 000',
        )), 'mise_a_jour', true);

        $this->assertSame('conflit', $lot->lignes()->where('numero', 2)->value('operation'));
        $this->assertTrue($lot->anomalies()->where('regle', 'credit_protege')->exists());
        $lot = $this->lots->soumettre($lot, $preparateur, true);
        $lot->update(['statut' => 'approuve']);
        $lot = app(ImportExecutionService::class)->lancer($lot->fresh(), $preparateur);

        $this->assertSame('termine', $lot->statut);
        $this->assertSame('7500000.00', LigneBudgetaire::firstWhere('code', 'LB-1')->montant_ouvert, 'Crédit officiel inchangé.');
        $this->assertNotNull(LigneBudgetaire::firstWhere('code', 'LB-2'));
    }

    public function test_budget_adopte_aucune_nouvelle_ligne_par_import(): void
    {
        $this->nomenclatureExistante();
        Budget::create(['exercice_id' => $this->exercice->id, 'reference' => 'BUD-2027', 'libelle' => 'Budget 2027', 'statut' => 'adopte']);
        $preparateur = $this->preparateur(['ligne_budgetaire.creer']);
        $lot = $this->lot($preparateur, 'lignes_budgetaires', $this->csv($this->lignesCsv('LB-9;Nouvelle;614300;614;;100')), 'insertion', true);

        $this->assertSame('anomalies_detectees', $lot->statut);
        $this->assertTrue($lot->anomalies()->where('regle', 'budget_protege')->exists());
    }

    // ── Référentiel organique ─────────────────────────────────────────────────────────────────

    public function test_structures_hierarchie_parent_dans_le_meme_fichier_et_ordre_d_execution(): void
    {
        Structure::firstOrCreate(['code' => 'SG'], ['libelle' => 'Secrétariat général', 'type' => 'secretariat_general', 'actif' => true]);
        $preparateur = $this->preparateur(['structure.importer']);
        $csv = "code;libelle;type;parent_code\nSVC-X;Service X;service;DIR-X\nDIR-X;Direction X;direction;SG\nSVC-BAD;Service mal rattaché;service;SG\n";
        $lot = $this->lot($preparateur, 'structures', $this->csv($csv));

        $this->assertTrue($lot->anomalies()->where('numero_ligne', 4)->where('regle', 'hierarchie')->exists(), 'Un service ne se rattache pas au SG.');
        $this->assertSame('inserer', $lot->lignes()->where('numero', 2)->value('operation'), 'Parent présent dans le même fichier : accepté.');
    }

    public function test_structure_existante_protegee_type_rattachement_et_responsable(): void
    {
        $responsable = User::factory()->create();
        $sg = Structure::firstOrCreate(['code' => 'SG'], ['libelle' => 'Secrétariat général', 'type' => 'secretariat_general', 'actif' => true]);
        $dir = Structure::create(['code' => 'DIR-A', 'libelle' => 'Direction A', 'type' => 'direction', 'parent_id' => $sg->id, 'responsable_user_id' => $responsable->id, 'actif' => true]);
        $preparateur = $this->preparateur(['structure.importer']);
        $lot = $this->lot($preparateur, 'structures', $this->csv("code;libelle;type;parent_code;responsable_email\nDIR-A;Direction A renommée;direction;SG;\n"), 'mise_a_jour');

        $this->assertSame('mettre_a_jour', $lot->lignes()->first()->operation);
        $this->assertArrayNotHasKey('responsable_user_id', $lot->lignes()->first()->differences ?? [], 'Un responsable vide dans le fichier n\'efface pas le responsable.');
        $lot->update(['statut' => 'approuve']);
        app(ImportExecutionService::class)->lancer($lot->fresh(), $preparateur);
        $this->assertSame('Direction A renommée', $dir->fresh()->libelle);
        $this->assertSame($responsable->id, $dir->fresh()->responsable_user_id);

        $conflit = $this->lot($preparateur, 'structures', $this->csv("code;libelle;type;parent_code\nDIR-A;Direction A renommée;cellule;SG\n"), 'mise_a_jour');
        $this->assertSame('conflit', $conflit->lignes()->first()->operation, 'Changement de type : procédure de l\'organigramme.');
    }

    // ── Suivi-évaluation : lot non sensible ───────────────────────────────────────────────────

    public function test_mesures_execution_directe_et_mesure_validee_protegee(): void
    {
        $this->seed(\Database\Seeders\RbmReferentielSeeder::class);
        $pilier = \App\Models\Pilier::create(['exercice_id' => $this->exercice->id, 'code' => 'P-T', 'intitule' => 'Pilier test']);
        $indicateur = Indicateur::create(['code' => 'IND-T1', 'libelle' => 'Indicateur test', 'periodicite' => 'trimestriel', 'valeur_cible' => 100, 'seuil_alerte' => 50,
            'rbm_type' => \App\Models\Pilier::class, 'rbm_id' => $pilier->id,
            'type_indicateur_id' => \App\Models\TypeIndicateur::where('code', 'OPE')->value('id'), 'unite_mesure_id' => \App\Models\UniteMesure::where('code', 'PCT')->value('id')]);
        $indicateur->mesures()->create(['periode' => '2026-T1', 'valeur_realisee' => 40, 'statut' => 'validee', 'version' => 1]);
        $preparateur = $this->preparateur(['indicateur.creer']);
        $lot = $this->lot($preparateur, 'mesures_indicateurs', $this->csv("indicateur_code;periode;valeur_realisee\nIND-T1;2026-T1;55\nIND-T1;2026-T2;62\nIND-ZZ;2026-T2;1\n"));

        $this->assertSame('anomalies_detectees', $lot->statut, 'Indicateur inconnu : bloquant.');
        $lot = $this->lot($preparateur, 'mesures_indicateurs', $this->csv("indicateur_code;periode;valeur_realisee\nIND-T1;2026-T1;55\nIND-T1;2026-T2;62\n"));
        $this->assertSame('conflit', $lot->lignes()->where('numero', 2)->value('operation'));
        $lot = app(ImportExecutionService::class)->lancer($lot, $preparateur);

        $this->assertSame('termine', $lot->statut, 'Domaine non sensible : exécution sans approbation.');
        $this->assertSame('saisie', $indicateur->mesures()->where('periode', '2026-T2')->value('statut'));
        $this->assertEquals(40.0, (float) $indicateur->mesures()->where('periode', '2026-T1')->value('valeur_realisee'), 'Mesure validée inchangée.');
    }

    // ── Cycle : rejet, annulation, dérive ─────────────────────────────────────────────────────

    public function test_rejet_par_l_approbateur(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $lot = $this->lots->soumettre($this->lot($preparateur, 'nomenclature', $this->csv($this->nomenclatureCsv())), $preparateur);
        $approbateur = $this->approbateurBudget();
        $this->actingAs($approbateur);
        app(DecisionMetierService::class)->decider($lot, $approbateur, ['decision' => 'rejeter', 'observation' => 'Codes non conformes.', 'categorie_rejet' => 'technique']);

        $this->assertSame('rejete', $lot->fresh()->statut);
        $this->assertSame('Codes non conformes.', $lot->fresh()->motif);
        $this->assertFalse(app(ImportExecutionService::class)->peutExecuter($lot->fresh()));
    }

    public function test_annulation_en_attente_d_approbation_clot_la_tache(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $lot = $this->lots->soumettre($this->lot($preparateur, 'nomenclature', $this->csv($this->nomenclatureCsv())), $preparateur);
        $this->lots->annuler($lot->fresh(), $preparateur, 'Fichier erroné.');

        $this->assertSame('annule', $lot->fresh()->statut);
        $this->assertFalse(WorkflowTache::query()->where('objet_type', $lot->getMorphClass())->where('objet_id', $lot->id)->ouvertes()->exists());
    }

    public function test_derive_de_la_base_entre_simulation_et_execution(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $lot = $this->lot($preparateur, 'nomenclature', $this->csv($this->nomenclatureCsv()));
        $lot->update(['statut' => 'approuve']);
        $this->nomenclatureExistante();   // 614300 créé entre-temps par ailleurs

        $this->expectExceptionOnce(fn () => app(ImportExecutionService::class)->lancer($lot->fresh(), $preparateur), 'a changé');
        $this->assertSame('simule', $lot->fresh()->statut, 'Plan recalculé : nouvelle validation requise.');
        $this->assertSame('ignorer', $lot->lignes()->where('numero', 2)->value('operation'), 'La rubrique existe désormais : plus d\'insertion.');
        $this->assertSame(1, BudgetRubrique::count());
    }

    // ── Sécurité des fichiers et des accès ────────────────────────────────────────────────────

    public function test_fichiers_maquilles_ou_dangereux_refuses(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $this->actingAs($preparateur);
        $lot = $this->lots->creer('nomenclature', 'insertion', null, $preparateur);

        $this->expectExceptionOnce(fn () => $this->lots->deposer($lot, $this->csv('ceci n\'est pas un classeur', 'faux.xlsx')), 'ne correspond pas');
        $this->expectExceptionOnce(fn () => $this->lots->deposer($lot, $this->csv("MZ\x90\x00binaire", 'programme.csv')), 'exécutable');
        $this->expectExceptionOnce(fn () => $this->lots->deposer($lot, $this->csv('x', 'script.php')), 'Format non accepté');
    }

    public function test_formule_injectee_signalee_et_neutralisee_dans_le_rapport(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $csv = "titre_code;article_code;paragraphe_code;rubrique_code;rubrique_intitule\n6;61;614;614300;=HYPERLINK(\"http://x\")\n";
        $lot = $this->lot($preparateur, 'nomenclature', $this->csv($csv));

        $this->assertTrue($lot->anomalies()->where('regle', 'injection_formule')->exists());
        $reponse = $this->actingAs($preparateur)->get(route('imports.rapport', [$lot, 'operations', 'csv']));
        $reponse->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $reponse->streamedContent());
    }

    public function test_acces_refuses_sans_permission_ou_sans_droit_metier(): void
    {
        $sansDroit = $this->utilisateur(['import.view']);
        $this->actingAs($sansDroit)->get(route('imports.create'))->assertForbidden();

        $sansMetier = $this->preparateur([]);
        $this->expectExceptionOnce(fn () => $this->lots->creer('lignes_budgetaires', 'insertion', $this->exercice->id, $sansMetier), 'droit métier');

        $preparateur = $this->preparateur(['nomenclature.creer']);
        $lot = $this->lot($preparateur, 'nomenclature', $this->csv($this->nomenclatureCsv()));
        $this->actingAs($this->preparateur(['nomenclature.creer']))->get(route('imports.show', $lot))->assertForbidden();
    }

    public function test_ecrans_du_module(): void
    {
        $preparateur = $this->preparateur(['nomenclature.creer']);
        $this->actingAs($preparateur)->get(route('imports.index'))->assertOk()->assertSee('Import de données');
        $this->actingAs($preparateur)->get(route('imports.create'))->assertOk()->assertSee('Nomenclature budgétaire');
        $this->actingAs($preparateur)->get(route('imports.modele', 'nomenclature'))->assertOk();

        $this->actingAs($preparateur)->post(route('imports.store'), ['domaine' => 'nomenclature', 'mode' => 'insertion', 'fichier' => $this->csv($this->nomenclatureCsv())])->assertRedirect();
        $lot = ImportLot::query()->latest('id')->firstOrFail();
        foreach (['synthese', 'correspondance', 'apercu', 'anomalies', 'historique'] as $onglet) {
            $this->actingAs($preparateur)->get(route('imports.show', [$lot, 'onglet' => $onglet]))->assertOk();
        }
    }

    private function expectExceptionOnce(callable $action, string $fragment): void
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());

            return;
        }
        $this->fail("Exception attendue contenant « {$fragment} ».");
    }
}
