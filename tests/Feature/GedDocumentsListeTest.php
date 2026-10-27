<?php

namespace Tests\Feature;

use App\Models\Ged\GedPartage;
use App\Models\User;
use App\Services\Ged\GedDocumentService;
use Database\Seeders\GedSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Registre documentaire /ged/documents : indicateurs, filtres, tri sécurisé, accès par ligne sans requête par ligne. */
class GedDocumentsListeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed(RoleSeeder::class);
        $this->seed(GedSeeder::class);
        Storage::fake('local');
    }

    private function user(string $role = 'directeur'): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function deposer(User $user, string $titre, array $extra = [], string $fichier = 'doc.pdf')
    {
        return app(GedDocumentService::class)->deposer(UploadedFile::fake()->create($fichier, 50, 'application/pdf'), ['titre' => $titre] + $extra, $user);
    }

    public function test_indicateurs_recherche_insensible_a_la_casse_et_raccourcis(): void
    {
        $directeur = $this->user();
        $autre = $this->user();
        $a = $this->deposer($directeur, 'Rapport annuel PAP', ['module_source' => 'PAP']);
        $b = $this->deposer($autre, 'Facture fournisseur', ['module_source' => 'BESOIN']);
        $b->update(['est_signe' => true]);

        $page = $this->actingAs($directeur)->get(route('ged.documents.index'))->assertOk()
            ->assertSee('Rapport annuel PAP')->assertSee('Facture fournisseur')->assertSee('Registre documentaire');
        $ind = $page->viewData('indicateurs');
        $this->assertSame(2, $ind['total']);
        $this->assertSame(1, $ind['signes']);
        $this->assertSame(1, $ind['mes']);

        $this->actingAs($directeur)->get(route('ged.documents.index', ['q' => 'rapport ANNUEL']))->assertSee($a->reference)->assertDontSee($b->reference);
        $this->actingAs($directeur)->get(route('ged.documents.index', ['vue' => 'signes']))->assertSee($b->reference)->assertDontSee($a->reference);
        $this->actingAs($directeur)->get(route('ged.documents.index', ['vue' => 'mes']))->assertSee($a->reference)->assertDontSee($b->reference);
        $this->actingAs($directeur)->get(route('ged.documents.index', ['module_source' => 'BESOIN']))->assertSee($b->reference)->assertDontSee($a->reference);
    }

    public function test_tri_non_autorise_ignore_et_tri_par_titre(): void
    {
        $user = $this->user();
        $this->deposer($user, 'Zeta');
        $this->deposer($user, 'Alpha');

        $this->actingAs($user)->get(route('ged.documents.index', ['tri' => 'titre; drop table ged_documents']))->assertOk();
        $docs = $this->actingAs($user)->get(route('ged.documents.index', ['tri' => 'titre', 'ordre' => 'asc']))->viewData('documents');
        $this->assertSame('Alpha', $docs->first()->titre);
    }

    public function test_droits_par_ligne_identiques_avec_prechargement_des_partages(): void
    {
        $proprietaire = $this->user('administrateur');
        $lecteur = $this->user('directeur');
        $partage = $this->deposer($proprietaire, 'Document partagé', ['niveau_confidentialite' => 'interne']);
        $nonPartage = $this->deposer($proprietaire, 'Document interne', ['niveau_confidentialite' => 'interne']);
        GedPartage::create(['document_id' => $partage->id, 'partage_avec' => $lecteur->id, 'peut_telecharger' => true, 'partage_par' => $proprietaire->id]);

        DB::enableQueryLog();
        $page = $this->actingAs($lecteur)->get(route('ged.documents.index'))->assertOk();
        $requetesPartage = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'ged_partages'))->count();

        // Même verdict que la règle d'accès évaluée sans préchargement (nouvelle instance du service).
        app()->forgetScopedInstances();
        foreach ([$partage, $nonPartage] as $doc) {
            $this->assertSame(app(\App\Services\Ged\GedAccessService::class)->peutTelecharger($lecteur, $doc->fresh()),
                str_contains($page->getContent(), route('ged.documents.download', $doc)), "Lien de téléchargement de {$doc->titre}");
        }
        $this->assertLessThanOrEqual(1, $requetesPartage, 'Une seule requête de partages pour toute la page.');
    }

    public function test_pages_derivees_et_acces(): void
    {
        $user = $this->user();
        $this->deposer($user, 'Doc dérivé');

        foreach (['ged.archives', 'ged.documents.confidentiels', 'ged.documents.expires', 'ged.partages'] as $route) {
            if (\Illuminate\Support\Facades\Route::has($route)) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }
        }
        $this->actingAs(User::factory()->create(['actif' => true, 'statut' => 'actif']))->get(route('ged.documents.index'))->assertForbidden();
    }
}
