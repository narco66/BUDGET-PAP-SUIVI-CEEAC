<?php

namespace Tests\Feature;

use App\Models\Encaissement;
use App\Models\Exercice;
use App\Models\Recette;
use App\Models\User;
use App\Services\Recettes\EncaissementService;
use Database\Seeders\RecettesPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/** Phase R3 — encaissements : saisie, justificatif obligatoire, validation, rapprochement. */
class EncaissementsR3Test extends TestCase
{
    use RefreshDatabase;

    private Exercice $exercice;
    private Recette $recette;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, RecettesPermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);

        // Recette ventilée (rubrique requise pour la comptabilisation d'un encaissement — garde R9).
        $titre = \App\Models\BudgetTitre::create(['code' => '7', 'intitule' => 'Recettes', 'sens' => 'recette', 'ordre' => 1]);
        $article = \App\Models\BudgetArticle::create(['titre_id' => $titre->id, 'code' => '72', 'intitule' => 'Contributions', 'ordre' => 1]);
        $paragraphe = \App\Models\BudgetParagraphe::create(['article_id' => $article->id, 'code' => '721', 'intitule' => 'États', 'ordre' => 1]);
        $rubrique = \App\Models\BudgetRubrique::create(['paragraphe_id' => $paragraphe->id, 'code' => '721010', 'intitule' => 'Contribution', 'ordre' => 1]);

        $this->recette = Recette::create([
            'exercice_id' => $this->exercice->id,
            'reference' => 'REC-ENC-1',
            'rubrique_id' => $rubrique->id,
            'montant_prevu' => 10_000_000,
            'montant_realise' => 0,
            'statut' => 'prevue',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    private function attacherPiece(Encaissement $e, User $u): void
    {
        $e->pieces()->create([
            'nom' => 'releve.pdf',
            'chemin' => 'ged/test/releve.pdf',
            'type_mime' => 'application/pdf',
            'taille' => 1024,
            'categorie' => 'justificatif',
            'televerse_par' => $u->id,
        ]);
    }

    /** Simule la fin du circuit workflow (comptabilisation directe du service). */
    private function enValidation(Encaissement $enc): Encaissement
    {
        $enc->update(['statut' => 'en_validation']);

        return $enc->fresh();
    }

    public function test_table_existe(): void
    {
        $this->assertTrue(Schema::hasTable('encaissements'));
    }

    public function test_saisie_avec_justificatifs_rattaches_a_la_ged(): void
    {
        $this->seed(\Database\Seeders\GedSeeder::class);
        Storage::fake('local');
        $user = $this->user('comptable');

        $response = $this->actingAs($user)->post(route('encaissements.store'), [
            'recette_id' => $this->recette->id,
            'reference' => 'ENC-UP',
            'montant' => 1_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
            'justificatifs' => [
                UploadedFile::fake()->create('releve.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('avis.pdf', 80, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect();
        $enc = Encaissement::firstWhere('reference', 'ENC-UP');
        $this->assertNotNull($enc);

        // Les justificatifs sont de vrais documents GED, liés polymorphiquement à l'encaissement.
        $this->assertSame(2, $enc->gedDocuments()->count());
        $this->assertDatabaseHas('ged_documents', [
            'attachable_type' => Encaissement::class,
            'attachable_id' => $enc->id,
            'module_source' => 'ENCAISS',
        ]);
    }

    public function test_validation_possible_avec_justificatif_ged(): void
    {
        $this->seed(\Database\Seeders\GedSeeder::class);
        Storage::fake('local');
        $saisisseur = $this->user('comptable');

        $enc = app(EncaissementService::class)->saisir([
            'recette_id' => $this->recette->id, 'reference' => 'ENC-GED',
            'montant' => 2_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $saisisseur);

        // Rattachement d'un justificatif GED puis validation autorisée.
        $this->actingAs($saisisseur)->post(route('encaissements.justificatifs.store', $enc), [
            'fichier' => UploadedFile::fake()->create('quittance.pdf', 60, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, $enc->fresh()->gedDocuments()->count());
        app(EncaissementService::class)->valider($this->enValidation($enc->fresh()), $this->user('controleur_financier'));
        $this->assertSame('valide', $enc->fresh()->statut);
    }

    public function test_saisie_calcule_la_contre_valeur_xaf(): void
    {
        $service = app(EncaissementService::class);
        $user = $this->user('comptable');

        $enc = $service->saisir([
            'recette_id' => $this->recette->id,
            'reference' => 'ENC-1',
            'montant' => 1000,
            'devise' => 'EUR',
            'taux_change' => 655.957,
            'mode_paiement' => 'virement',
            'date_encaissement' => '2026-03-01',
        ], $user);

        $this->assertSame('saisi', $enc->statut);
        $this->assertEqualsWithDelta(655957.0, (float) $enc->montant_xaf, 0.01);
    }

    public function test_validation_impossible_sans_justificatif(): void
    {
        $service = app(EncaissementService::class);
        $user = $this->user('comptable');

        $enc = $service->saisir([
            'recette_id' => $this->recette->id, 'reference' => 'ENC-2',
            'montant' => 5_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $user);

        $this->expectException(RuntimeException::class);
        $service->valider($this->enValidation($enc), $this->user('controleur_financier'));
    }

    public function test_validation_applique_le_realise_sur_la_recette(): void
    {
        $service = app(EncaissementService::class);
        $saisisseur = $this->user('comptable');

        $enc = $service->saisir([
            'recette_id' => $this->recette->id, 'reference' => 'ENC-3',
            'montant' => 4_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $saisisseur);
        $this->attacherPiece($enc, $saisisseur);

        $service->valider($this->enValidation($enc), $this->user('controleur_financier'));

        $this->recette->refresh();
        $this->assertEqualsWithDelta(4_000_000.0, (float) $this->recette->montant_realise, 0.01);
        $this->assertSame('partiellement_realisee', $this->recette->statut);
    }

    public function test_encaissement_comptabilise_ne_peut_pas_etre_supprime(): void
    {
        $service = app(EncaissementService::class);
        $saisisseur = $this->user('comptable');

        $enc = $service->saisir([
            'recette_id' => $this->recette->id, 'reference' => 'ENC-4',
            'montant' => 1_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $saisisseur);
        $this->attacherPiece($enc, $saisisseur);
        $enc = $service->valider($this->enValidation($enc), $this->user('controleur_financier'));

        $this->expectException(RuntimeException::class);
        $enc->delete();
    }

    public function test_rapprochement_bancaire(): void
    {
        $service = app(EncaissementService::class);
        $saisisseur = $this->user('comptable');

        $enc = $service->saisir([
            'recette_id' => $this->recette->id, 'reference' => 'ENC-5',
            'montant' => 1_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $saisisseur);
        $this->attacherPiece($enc, $saisisseur);
        $enc = $service->valider($this->enValidation($enc), $this->user('controleur_financier'));

        $service->rapprocher($enc, ['date_rapprochement' => '2026-03-05', 'reference_bancaire' => 'VIR-998'], $saisisseur);

        $enc->refresh();
        $this->assertSame('rapproche', $enc->statut);
        $this->assertTrue($enc->rapproche);
        $this->assertSame('VIR-998', $enc->reference_bancaire);
    }

    public function test_separation_saisie_et_validation_via_http(): void
    {
        // Le comptable saisit (recette.encaissement) mais ne valide pas.
        $comptable = $this->user('comptable');
        $this->actingAs($comptable)->get(route('encaissements.create'))->assertOk();

        // Le contrôleur valide (recette.valider) mais ne saisit pas.
        $controleur = $this->user('controleur_financier');
        $this->actingAs($controleur)->get(route('encaissements.create'))->assertForbidden();
    }

    public function test_fiche_non_soumise_sans_panneau_de_decision_meme_pour_administrateur(): void
    {
        // Le contournement administrateur de la Gate ne doit pas afficher « Votre décision » sans circuit en cours.
        $admin = $this->user('administrateur');
        $enc = app(EncaissementService::class)->saisir([
            'recette_id' => $this->recette->id, 'reference' => 'ENC-UI-1',
            'montant' => 1_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $admin);

        $this->actingAs($admin)->get(route('encaissements.show', $enc))
            ->assertOk()
            ->assertDontSee('Votre décision')
            ->assertSee('Soumission au circuit de validation impossible')
            ->assertSee('Ajouter un justificatif');
    }

    public function test_fiche_signale_recette_non_ventilee(): void
    {
        $admin = $this->user('administrateur');
        $this->recette->update(['rubrique_id' => null]);
        $enc = app(EncaissementService::class)->saisir([
            'recette_id' => $this->recette->id, 'reference' => 'ENC-UI-2',
            'montant' => 1_000_000, 'devise' => 'XAF', 'taux_change' => 1,
            'mode_paiement' => 'virement', 'date_encaissement' => '2026-03-01',
        ], $admin);
        $this->attacherPiece($enc, $admin);

        $this->actingAs($admin)->get(route('encaissements.show', $enc))
            ->assertOk()
            ->assertSee('Ventiler la recette')
            ->assertSee(route('recettes.show', $this->recette).'#ventilation', false);
    }
}
