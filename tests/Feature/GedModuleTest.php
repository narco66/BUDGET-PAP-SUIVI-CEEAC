<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Ged\GedDocument;
use App\Models\User;
use App\Services\Ged\GedArchiveService;
use App\Services\Ged\GedAttachmentService;
use App\Services\Ged\GedDocumentService;
use App\Services\Ged\GedSearchService;
use App\Services\Ged\GedSignatureService;
use Database\Seeders\GedSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GedModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(GedSeeder::class);
        Storage::fake('local');
    }

    private function user(string $role = 'directeur'): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function fichierTest(): UploadedFile
    {
        return UploadedFile::fake()->create('test-doc.pdf', 100, 'application/pdf');
    }

    public function test_depot_document(): void
    {
        $user = $this->user();
        $service = app(GedDocumentService::class);

        $doc = $service->deposer($this->fichierTest(), [
            'titre' => 'Document test GED',
            'module_source' => 'GED',
            'type_code' => 'RAPPORT',
        ], $user);

        $this->assertDatabaseHas('ged_documents', ['titre' => 'Document test GED', 'created_by' => $user->id]);
        $this->assertDatabaseHas('ged_versions', ['document_id' => $doc->id, 'numero' => 1]);
        $this->assertNotNull($doc->hash_sha256);
        $this->assertStringStartsWith('CEEAC/GED/', $doc->reference);
        Storage::disk('local')->assertExists($doc->chemin_fichier);
    }

    public function test_dashboard_accessible(): void
    {
        $user = $this->user('lecteur');

        $this->actingAs($user)->get(route('ged.dashboard'))->assertOk();
    }

    public function test_liste_documents_filtree(): void
    {
        $user = $this->user();
        $service = app(GedDocumentService::class);
        $service->deposer($this->fichierTest(), ['titre' => 'Doc A', 'module_source' => 'PAP'], $user);

        $results = app(GedSearchService::class)->rechercher(['module_source' => 'PAP'], $user);

        $this->assertGreaterThanOrEqual(1, $results->total());
    }

    public function test_versionning(): void
    {
        $user = $this->user();
        $service = app(GedDocumentService::class);
        $doc = $service->deposer($this->fichierTest(), ['titre' => 'Versionné'], $user);

        $service->nouvelleVersion($doc, UploadedFile::fake()->create('v2.pdf', 120, 'application/pdf'), $user, 'Mise à jour');

        $doc->refresh();
        $this->assertEquals(2, $doc->version_courante);
        $this->assertDatabaseCount('ged_versions', 2);
    }

    public function test_archivage_et_restauration(): void
    {
        $user = $this->user('administrateur');
        $docService = app(GedDocumentService::class);
        $archiveService = app(GedArchiveService::class);

        $doc = $docService->deposer($this->fichierTest(), ['titre' => 'À archiver'], $user);
        $archiveService->archiver($doc, $user, 'Fin de cycle');

        $doc->refresh();
        $this->assertTrue($doc->est_archive);
        $this->assertTrue($doc->est_verrouille);

        $archiveService->restaurer($doc, $user, 'Consultation exceptionnelle');
        $doc->refresh();
        $this->assertFalse($doc->est_archive);
    }

    public function test_signature_electronique(): void
    {
        $createur = $this->user('directeur');
        $signataire = $this->user('controleur_financier');

        $doc = app(GedDocumentService::class)->deposer($this->fichierTest(), ['titre' => 'À signer'], $createur);

        $sigService = app(GedSignatureService::class);
        $signature = $sigService->demander($doc, $createur, $signataire);
        $sigService->signer($signature, $signataire);

        $doc->refresh();
        $this->assertTrue($doc->est_signe);
        $this->assertTrue($doc->est_verrouille);
    }

    public function test_telechargement_securise(): void
    {
        $user = $this->user();
        $doc = app(GedDocumentService::class)->deposer($this->fichierTest(), ['titre' => 'Download test'], $user);

        $this->actingAs($user)->get(route('ged.documents.download', $doc))->assertOk();
    }

    public function test_acces_refuse_sans_permission(): void
    {
        $user = User::factory()->create(['actif' => true]);
        $this->actingAs($user)->get(route('ged.dashboard'))->assertForbidden();
    }

    public function test_journal_apres_depot(): void
    {
        $user = $this->user();
        app(GedDocumentService::class)->deposer($this->fichierTest(), ['titre' => 'Journalisé'], $user);

        $this->assertDatabaseHas('ged_journaux', ['action' => 'creation']);
    }

    public function test_page_verification_publique(): void
    {
        $user = $this->user();
        $doc = app(GedDocumentService::class)->deposer($this->fichierTest(), ['titre' => 'Public check'], $user);

        $this->get(route('verification.ged', $doc->uuid))->assertOk();
    }

    public function test_notification_apres_depot(): void
    {
        Notification::fake();
        $destinataire = $this->user('lecteur');
        $user = $this->user('directeur');

        app(GedDocumentService::class)->deposer($this->fichierTest(), ['titre' => 'Notifié'], $user);

        Notification::assertSentTo($destinataire, \App\Notifications\Ged\DocumentDeposeNotification::class);
    }

    public function test_rattachement_engagement(): void
    {
        $user = $this->user('gestionnaire_budgetaire');
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $engagement = Engagement::create([
            'exercice_id' => $exercice->id,
            'numero' => 'ENG-TEST-001',
            'objet' => 'Test GED',
            'type' => 'simple',
            'statut' => 'brouillon',
            'montant_total' => 1000,
            'cree_par' => $user->id,
        ]);

        $attach = app(GedAttachmentService::class);
        $doc = $attach->rattacherDepuisModule(
            'engagement',
            $engagement->id,
            UploadedFile::fake()->create('justif.pdf', 50, 'application/pdf'),
            ['titre' => 'Justificatif engagement'],
            $user,
            app(GedDocumentService::class),
        );

        $this->assertEquals(Engagement::class, $doc->attachable_type);
        $this->assertEquals($engagement->id, $doc->attachable_id);
    }

    public function test_rattachement_depuis_module_utilise_nom_fichier_si_titre_vide(): void
    {
        $user = $this->user('administrateur');
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $besoin = ExpressionBesoin::create([
            'exercice_id' => $exercice->id,
            'numero' => 'BES-TEST-001',
            'objet' => 'Besoin GED sans titre',
            'montant_estime' => 1000,
            'statut' => 'brouillon',
        ]);

        $this->actingAs($user)->post(route('ged.attach.store', ['besoin', $besoin->id]), [
            'fichier' => UploadedFile::fake()->create('rapport-pap.pdf', 50, 'application/pdf'),
            'titre' => '',
        ])->assertRedirect()->assertSessionHas('succes');

        $this->assertDatabaseHas('ged_documents', [
            'titre' => 'rapport-pap.pdf',
            'attachable_type' => ExpressionBesoin::class,
            'attachable_id' => $besoin->id,
        ]);
    }

    public function test_export_registre_excel(): void
    {
        $user = $this->user('administrateur');
        app(GedDocumentService::class)->deposer($this->fichierTest(), ['titre' => 'Export'], $user);

        $this->actingAs($user)->get(route('ged.exports.documents.excel'))->assertOk();
    }

    public function test_ocr_basique_fichier_texte(): void
    {
        $user = $this->user();
        $fichier = UploadedFile::fake()->createWithContent('note.txt', 'Contenu OCR test CEEAC');

        $doc = app(GedDocumentService::class)->deposer($fichier, ['titre' => 'Texte OCR'], $user);

        $this->assertStringContainsString('Contenu OCR test', $doc->fresh()->contenu_ocr ?? '');
    }

    public function test_commande_alerte_expiration(): void
    {
        $user = $this->user();
        $doc = app(GedDocumentService::class)->deposer($this->fichierTest(), [
            'titre' => 'Expire bientôt',
            'date_expiration' => now()->addDays(10)->toDateString(),
        ], $user);

        $this->assertEquals(0, Artisan::call('ged:expiration-alerte', ['--jours' => 30]));
    }

    public function test_indexation_rapport_reporting(): void
    {
        $user = $this->user('administrateur');
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        $rapport = \App\Models\Rapport::create([
            'reference' => 'RPT-2026-000001',
            'type' => 'pap',
            'titre' => 'Rapport PAP test',
            'exercice_id' => $exercice->id,
            'periode' => '2026',
            'format' => 'pdf',
            'chemin' => 'rapports/2026/test.pdf',
            'taille' => 100,
            'hash' => hash('sha256', 'test'),
            'qr_token' => 'token-test-123',
            'version' => 1,
            'statut' => 'genere',
            'genere_par' => $user->id,
        ]);

        Storage::disk('local')->put($rapport->chemin, 'contenu test pdf');

        $document = app(\App\Services\Ged\GedRapportBridgeService::class)->indexer($rapport, $user);

        $this->assertEquals('REPORTING', $document->module_source);
        $this->assertEquals($rapport->id, $document->module_source_id);
    }

    public function test_recherche_sauvegardee(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('ged.recherche.sauvegarder'), [
            'nom' => 'Mes PAP',
            'module_source' => 'PAP',
            'q' => 'budget',
        ])->assertRedirect();

        $this->assertDatabaseHas('ged_recherches_sauvegardees', [
            'utilisateur_id' => $user->id,
            'nom' => 'Mes PAP',
        ]);
    }

    public function test_commande_ocr_batch(): void
    {
        $user = $this->user();
        app(GedDocumentService::class)->deposer(
            UploadedFile::fake()->createWithContent('ocr.txt', 'Texte indexable GED'),
            ['titre' => 'OCR batch'],
            $user,
        );

        Artisan::call('ged:ocr-traiter', ['--limit' => 10]);

        $this->assertDatabaseHas('ged_documents', [
            'titre' => 'OCR batch',
        ]);

        $doc = GedDocument::where('titre', 'OCR batch')->first();
        $this->assertStringContainsString('Texte indexable', $doc->contenu_ocr ?? '');
    }
}
