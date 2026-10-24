<?php

namespace Tests\Feature;

use App\Enums\Ged\NiveauConfidentialite;
use App\Models\User;
use App\Services\Ged\GedDocumentService;
use Database\Seeders\GedSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GedSpaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, GedSeeder::class]);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_lecteur_sans_ged_est_refuse(): void
    {
        $this->actingAs($this->user('point_focal'))
            ->getJson(route('api.ged.hub'))
            ->assertForbidden();
    }

    public function test_hub_liste_et_fiche_metadonnees(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $doc = app(GedDocumentService::class)->deposer(
            UploadedFile::fake()->create('note.pdf', 40, 'application/pdf'),
            ['titre' => 'Note budgétaire SPA', 'module_source' => 'BUDGET', 'type_code' => 'RAPPORT'],
            $gestionnaire,
        );

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.hub'))
            ->assertOk()
            ->assertJsonPath('indicateurs.total', 1)
            ->assertJsonPath('recents.0.titre', 'Note budgétaire SPA')
            ->assertJsonMissingPath('recents.0.chemin_fichier');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.documents.index', ['q' => 'Note budgétaire']))
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $doc->uuid)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.documents.show', $doc->uuid))
            ->assertOk()
            ->assertJsonPath('data.reference', $doc->reference)
            ->assertJsonPath('data.versions.0.numero', 1)
            ->assertJsonMissingPath('data.chemin_fichier');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.recherche', ['q' => 'Note budgétaire']))
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $doc->uuid)
            ->assertJsonMissingPath('data.0.chemin_fichier');

        $this->actingAs($gestionnaire)
            ->postJson(route('api.ged.recherche.sauvegarder'), [
                'nom' => 'Notes SPA',
                'filtres' => ['q' => 'Note'],
            ])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Notes SPA');
    }

    public function test_api_classement_vues_et_referentiel(): void
    {
        $this->seed(\Database\Seeders\GedPermissionsSeeder::class);
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $admin = $this->user('administrateur');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.classement'))
            ->assertOk()
            ->assertJsonPath('data.codification', 'CEEAC/GED/{MODULE}/{TYPE}/{ANNEE}/{NUMERO}');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.vues', 'archives'))
            ->assertOk()
            ->assertJsonPath('vue', 'archives');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.versions'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.chemin_fichier');

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.categories'))
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->getJson(route('api.ged.journal'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('api.ged.journal'))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('api.ged.categories'))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('api.ged.parametres'))
            ->assertOk()
            ->assertJsonPath('data.taille_max_mo', config('ged.taille_max_mo', 10));
    }

    public function test_document_tres_confidentiel_masque_aux_autres(): void
    {
        $auteur = $this->user('directeur');
        $lecteur = $this->user('lecteur');
        $secret = app(GedDocumentService::class)->deposer(
            UploadedFile::fake()->create('secret.pdf', 20, 'application/pdf'),
            [
                'titre' => 'Secret cabinet',
                'module_source' => 'GED',
                'niveau_confidentialite' => NiveauConfidentialite::TresConfidentiel,
            ],
            $auteur,
        );

        $this->actingAs($lecteur)
            ->getJson(route('api.ged.documents.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($lecteur)
            ->getJson(route('api.ged.documents.show', $secret->uuid))
            ->assertForbidden();
    }

    public function test_depot_session_sans_binaire_et_idempotent(): void
    {
        $directeur = $this->user('directeur');
        $gestionnaire = $this->user('gestionnaire_budgetaire');

        $this->actingAs($gestionnaire)
            ->post(route('api.ged.documents.store'), [
                'titre' => 'Refus',
                'fichier' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($directeur)
            ->post(route('api.ged.documents.store'), [
                'titre' => 'Sans fichier',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $cle = 'ged-depot-spa-1';
        $reponse = $this->actingAs($directeur)
            ->post(route('api.ged.documents.store'), [
                'titre' => 'Note déposée SPA',
                'module_source' => 'BUDGET',
                'fichier' => UploadedFile::fake()->create('note-spa.pdf', 30, 'application/pdf'),
            ], ['Accept' => 'application/json', 'Idempotency-Key' => $cle]);

        $reponse->assertCreated()
            ->assertJsonPath('code', 'depose')
            ->assertJsonPath('data.titre', 'Note déposée SPA')
            ->assertJsonMissingPath('data.chemin_fichier');

        $uuid = $reponse->json('data.uuid');
        $this->assertNotEmpty($uuid);

        $this->actingAs($directeur)
            ->post(route('api.ged.documents.store'), [
                'titre' => 'Note déposée SPA',
                'fichier' => UploadedFile::fake()->create('note-spa-2.pdf', 30, 'application/pdf'),
            ], ['Accept' => 'application/json', 'Idempotency-Key' => $cle])
            ->assertCreated()
            ->assertJsonPath('data.uuid', $uuid);

        $this->assertSame(1, \App\Models\Ged\GedDocument::query()->where('titre', 'Note déposée SPA')->count());
    }

    public function test_demande_et_signature_par_le_signataire(): void
    {
        $directeur = $this->user('directeur');
        $controleur = $this->user('controleur_financier');
        $doc = app(GedDocumentService::class)->deposer(
            UploadedFile::fake()->create('acte.pdf', 25, 'application/pdf'),
            ['titre' => 'Acte à viser', 'module_source' => 'GED'],
            $directeur,
        );

        $this->actingAs($directeur)
            ->postJson(route('api.ged.documents.signatures', $doc->uuid), [
                'signataire_id' => $controleur->id,
            ])
            ->assertForbidden();

        $demande = $this->actingAs($controleur)
            ->postJson(route('api.ged.documents.signatures', $doc->uuid), [
                'signataire_id' => $controleur->id,
            ]);
        $demande->assertCreated()->assertJsonPath('data.statut', 'en_attente');
        $signatureId = $demande->json('data.id');

        $this->actingAs($controleur)
            ->getJson(route('api.ged.signatures.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $signatureId)
            ->assertJsonPath('data.0.peut_signer', true);

        $this->actingAs($controleur)
            ->postJson(route('api.ged.signatures.signer', $signatureId))
            ->assertOk()
            ->assertJsonPath('code', 'signe')
            ->assertJsonPath('data.statut', 'signe')
            ->assertJsonMissingPath('data.certificat_interne');

        $this->actingAs($controleur)
            ->getJson(route('api.ged.documents.show', $doc->uuid))
            ->assertOk()
            ->assertJsonPath('data.est_signe', true)
            ->assertJsonMissingPath('data.chemin_fichier');
    }
}
