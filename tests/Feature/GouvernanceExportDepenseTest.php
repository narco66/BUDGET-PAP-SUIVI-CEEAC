<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0 — Gouvernance unifiée : l'archivage d'un export de la chaîne de dépense produit un rapport
 * officiel traçable (versioning, hash, QR) sans altérer l'export rapide existant.
 */
class GouvernanceExportDepenseTest extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(DepensePermissionsSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    public function test_archive_cree_un_rapport_gouverne_et_archive_le_fichier(): void
    {
        // « president » possède rapport.exporter (⇒ depenses.export) mais pas ged.creer : chemin ciblé sans GED.
        $auteur = $this->user('president');

        $reponse = $this->actingAs($auteur)
            ->get(route('depense.export.archiver', ['cle' => 'besoins', 'exercice' => 2026]));

        $reponse->assertRedirect();
        $reponse->assertSessionHas('succes');

        $rapport = Rapport::where('type', 'export_depense_besoins')->first();

        $this->assertNotNull($rapport, 'Un rapport gouverné doit être enregistré.');
        $this->assertSame('Journal des expressions de besoin', $rapport->titre);
        $this->assertSame('excel', $rapport->format);
        $this->assertSame($this->exercice->id, $rapport->exercice_id);
        $this->assertSame(1, $rapport->version);
        $this->assertSame('genere', $rapport->statut);
        $this->assertSame('genere', $rapport->statut_workflow);
        $this->assertNotEmpty($rapport->hash);
        $this->assertNotEmpty($rapport->qr_token);
        $this->assertSame($auteur->id, $rapport->genere_par);
        Storage::disk('local')->assertExists($rapport->chemin);
    }

    public function test_archive_csv_incremente_la_version_par_format(): void
    {
        $auteur = $this->user('president');

        $this->actingAs($auteur)->get(route('depense.export.archiver', ['cle' => 'besoins', 'exercice' => 2026]));
        $this->actingAs($auteur)->get(route('depense.export.archiver', ['cle' => 'besoins', 'exercice' => 2026]));
        $this->actingAs($auteur)->get(route('depense.export.archiver', ['cle' => 'besoins', 'format' => 'csv', 'exercice' => 2026]));

        // Versioning indépendant par (type + exercice + période + format).
        $this->assertSame(2, Rapport::where('type', 'export_depense_besoins')->where('format', 'excel')->max('version'));
        $this->assertSame(1, Rapport::where('type', 'export_depense_besoins')->where('format', 'csv')->max('version'));
    }

    public function test_archive_refuse_sans_permission_export(): void
    {
        $lecteur = $this->user('lecteur'); // *.voir seulement

        $this->actingAs($lecteur)
            ->get(route('depense.export.archiver', ['cle' => 'besoins', 'exercice' => 2026]))
            ->assertForbidden();

        $this->assertSame(0, Rapport::count());
    }

    public function test_cle_inconnue_retourne_404(): void
    {
        $this->actingAs($this->user('president'))
            ->get(route('depense.export.archiver', ['cle' => 'inexistant', 'exercice' => 2026]))
            ->assertNotFound();
    }

    public function test_export_rapide_existant_reste_disponible(): void
    {
        // Non-régression : le téléchargement direct fonctionne toujours et ne crée aucun rapport.
        $this->actingAs($this->user('president'))
            ->get(route('depense.export.besoins', ['exercice' => 2026]))
            ->assertOk();

        $this->assertSame(0, Rapport::count());
    }
}
