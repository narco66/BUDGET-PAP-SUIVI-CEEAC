<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Ged\GedDocument;
use App\Models\PieceJustificative;
use App\Models\User;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Tout document joint et tout document généré sont intégrés automatiquement à la GED. */
class GedIntegrationAutoTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private ExpressionBesoin $besoin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        Storage::fake('local');
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class]);
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        [$activite, $ligne] = $this->activiteEtLigne($exercice, 'LB-GED', 100_000);
        $this->admin = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('administrateur');
        $this->besoin = ExpressionBesoin::create([
            'numero' => 'EB-GED-1', 'exercice_id' => $exercice->id, 'activite_id' => $activite->id, 'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $this->admin->id, 'objet' => 'Besoin GED', 'montant_estime' => 500, 'priorite' => 'moyenne', 'statut' => 'brouillon',
        ]);
    }

    private function joindre(): PieceJustificative
    {
        $this->actingAs($this->admin)->post(route('pieces.store', ['besoin', $this->besoin->id]), [
            'fichier' => UploadedFile::fake()->createWithContent('devis.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"), 'categorie' => 'devis',
        ])->assertSessionHasNoErrors();

        return PieceJustificative::firstOrFail();
    }

    public function test_piece_jointe_integree_a_la_ged_avec_son_dossier(): void
    {
        $piece = $this->joindre();

        $document = GedDocument::findOrFail($piece->fresh()->ged_document_id);
        $this->assertSame('BESOIN', $document->module_source);
        $this->assertSame(ExpressionBesoin::class, $document->attachable_type);
        $this->assertSame($this->besoin->id, (int) $document->attachable_id);
        $this->assertSame($piece->chemin, $document->chemin_fichier, 'Le fichier est référencé, pas dupliqué.');
        $this->assertSame(hash('sha256', Storage::disk('local')->get($piece->chemin)), $document->hash_sha256);
        $this->assertSame(1, $document->versions()->count());
    }

    public function test_document_ged_de_l_eb_visible_sur_l_engagement(): void
    {
        $this->joindre();
        $engagement = \App\Models\Engagement::create([
            'exercice_id' => $this->besoin->exercice_id, 'expression_besoin_id' => $this->besoin->id, 'numero' => 'ENG-GED-1',
            'type' => 'simple', 'objet' => 'ENG', 'montant_total' => 500, 'statut' => 'brouillon', 'credits_reserves' => false,
        ]);

        $this->actingAs($this->admin)->get(route('engagements.show', $engagement))->assertOk()
            ->assertSee('Documents GED (1)')->assertSee('Expression de besoin EB-GED-1');
    }

    public function test_piece_retiree_le_document_ged_est_archive_et_le_fichier_conserve(): void
    {
        $piece = $this->joindre()->fresh();
        $this->actingAs($this->admin)->delete(route('pieces.destroy', $piece))->assertRedirect();

        $document = GedDocument::findOrFail($piece->ged_document_id);
        $this->assertTrue((bool) $document->est_archive);
        Storage::disk('local')->assertExists($piece->chemin);
    }

    public function test_pdf_de_l_eb_genere_est_archive_une_fois_par_statut(): void
    {
        $this->actingAs($this->admin)->get(route('besoins.pdf', $this->besoin))->assertOk();
        $this->actingAs($this->admin)->get(route('besoins.pdf', $this->besoin))->assertOk();
        $documents = GedDocument::where('attachable_type', ExpressionBesoin::class)->where('attachable_id', $this->besoin->id)->get();
        $this->assertCount(1, $documents, 'Une consultation répétée au même statut ne duplique pas le document.');
        $this->assertSame('besoins.pdf', $documents->first()->metadonnees_metier['route']);
        $this->assertSame('brouillon', $documents->first()->metadonnees_metier['statut_dossier']);
        Storage::disk('local')->assertExists($documents->first()->chemin_fichier);

        $this->besoin->update(['statut' => 'soumis']);
        $this->actingAs($this->admin)->get(route('besoins.pdf', $this->besoin))->assertOk();
        $this->assertSame(2, GedDocument::where('attachable_id', $this->besoin->id)->where('attachable_type', ExpressionBesoin::class)->count());
    }

    public function test_export_sans_dossier_integre_a_la_ged(): void
    {
        $this->actingAs($this->admin)->get(route('risques.export', 'pdf'))->assertOk();

        $document = GedDocument::where('module_source', 'EXPORT')->firstOrFail();
        $this->assertSame('risques.export', $document->metadonnees_metier['route']);
        $this->assertSame('pdf', $document->extension);
    }

    public function test_export_en_flux_continu_integre_et_contenu_intact(): void
    {
        $reponse = $this->actingAs($this->admin)->get(route('taches.export'));
        $reponse->assertOk();
        $contenu = $reponse->streamedContent();   // la réponse reste un flux ; l'intégration a lieu pendant l'envoi
        $document = GedDocument::where('metadonnees_metier->route', 'taches.export')->firstOrFail();
        $this->assertSame(hash('sha256', $contenu), $document->hash_sha256, 'Le fichier téléchargé et celui de la GED sont identiques.');
    }

    public function test_telecharger_une_piece_existante_ne_cree_pas_de_doublon(): void
    {
        $piece = $this->joindre();
        $avant = GedDocument::count();

        $this->actingAs($this->admin)->get(route('pieces.download', $piece))->assertOk();

        $this->assertSame($avant, GedDocument::count());
    }

    public function test_rattrapage_des_pieces_existantes(): void
    {
        $piece = PieceJustificative::withoutEvents(fn () => $this->besoin->pieces()->create([
            'nom' => 'ancien.pdf', 'chemin' => 'ged/ancien.pdf', 'type_mime' => 'application/pdf', 'taille' => 10, 'categorie' => 'justificatif',
        ]));
        Storage::disk('local')->put('ged/ancien.pdf', 'contenu');
        $this->assertNull($piece->ged_document_id);

        $this->artisan('ged:integrer-existants')->assertSuccessful();

        $this->assertNotNull($piece->fresh()->ged_document_id);
    }
}
