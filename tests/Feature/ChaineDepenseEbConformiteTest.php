<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Services\Depense\BesoinVersEngagementService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

class ChaineDepenseEbConformiteTest extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_eb_pap_herite_et_produit_un_seul_engagement(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $commissaire = $this->user('commissaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-PAP', 5000);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Besoin PAP',
            'lignes' => [
                ['libelle' => 'Article A', 'quantite' => 2, 'unite' => 'u', 'prix_unitaire' => 100, 'taxe' => 10],
                ['libelle' => 'Article B', 'quantite' => 1, 'unite' => 'u', 'prix_unitaire' => 50, 'taxe' => 0],
            ],
        ])->assertCreated()
            ->assertJsonPath('besoin.categorie', 'pap')
            ->assertJsonPath('besoin.montant_estime', '260.00')
            ->assertJsonPath('besoin.heritage_pap.activite.id', $activite->id);

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->assertCount(2, $besoin->lignes);

        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertOk();
        $this->assertSame('BESOIN_PAP', $besoin->fresh()->workflowActif->workflow->code);

        $this->actingAs($directeur)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])->assertOk();
        $reponse = $this->actingAs($commissaire)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])
            ->assertOk()
            ->assertJsonPath('besoin.statut', 'valide');
        $this->assertGreaterThan(0, (int) $reponse->json('besoin.engagement_id'));

        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());

        app(BesoinVersEngagementService::class)->assurerDepuisBesoinApprouve($besoin->fresh(), $chef);
        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
    }

    public function test_eb_hors_pap_refuse_l_activite_et_suit_drhmg_sg_president(): void
    {
        $chef = $this->user('chef_service');
        $drhmg = $this->user('directeur');
        $sg = $this->user('secretaire_general');
        $president = $this->user('president');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-HP', 8000);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'hors_pap',
            'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id,
            'objet' => 'Hors PAP invalide',
            'justification' => 'Motif',
            'montant_estime' => 100,
        ])->assertUnprocessable();

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'hors_pap',
            'ligne_budgetaire_id' => $ligne->id,
            'objet' => 'Fournitures hors PAP',
            'justification' => 'Besoin de fonctionnement non programmé.',
            'montant_estime' => 120,
        ])->assertCreated()
            ->assertJsonPath('besoin.categorie', 'hors_pap')
            ->assertJsonPath('besoin.heritage_pap', null);

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertOk();
        $this->assertSame('BESOIN_HORS_PAP', $besoin->fresh()->workflowActif->workflow->code);

        $this->actingAs($drhmg)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])->assertOk();
        $this->actingAs($sg)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])->assertOk();
        $this->actingAs($president)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])
            ->assertOk()
            ->assertJsonPath('besoin.statut', 'valide');

        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
    }

    public function test_alerte_credits_non_bloquante_a_la_soumission(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-AL', 50);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'activite_id' => $activite->id,
            'objet' => 'Montant élevé',
            'montant_estime' => 200,
        ])->assertCreated();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))
            ->assertOk()
            ->assertJsonPath('besoin.alerte_credits.deficit', '150.00');
    }

    public function test_retour_pour_correction_passe_en_a_corriger(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $commissaire = $this->user('commissaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-RET', 2000);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'À corriger',
            'montant_estime' => 100,
        ])->assertCreated();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertOk();
        $this->actingAs($directeur)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])->assertOk();
        $this->actingAs($commissaire)->postJson(route('api.besoins.decisions', $besoin->fresh()), [
            'decision' => 'renvoyer',
            'observation' => 'Compléter la justification',
        ])->assertOk()->assertJsonPath('besoin.statut', 'a_corriger');

        $this->assertTrue($besoin->fresh()->estModifiable());

        $this->actingAs($chef)->putJson(route('api.besoins.update', $besoin->fresh()), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Objet corrigé',
            'montant_estime' => 100,
        ])->assertOk()->assertJsonPath('besoin.objet', 'Objet corrigé');
    }

    public function test_categorie_figee_apres_soumission(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-CAT', 3000);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'hors_pap',
            'ligne_budgetaire_id' => $ligne->id,
            'objet' => 'Hors PAP figé',
            'justification' => 'Fonctionnement',
            'montant_estime' => 80,
        ])->assertCreated();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertOk();
        $besoin->update(['statut' => 'a_corriger']);

        $this->actingAs($chef)->putJson(route('api.besoins.update', $besoin->fresh()), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Tentative PAP',
            'montant_estime' => 80,
        ])->assertStatus(422);
        $this->assertSame('hors_pap', $besoin->fresh()->categorie);
    }

    public function test_suppression_eb_interdite_si_engagement_lie(): void
    {
        $admin = $this->user('administrateur');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-DEL', 1000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $admin, 100, 'EB-DEL');
        $besoin->update(['statut' => 'brouillon']);
        Engagement::create([
            'numero' => 'ENG-DEL',
            'exercice_id' => $this->exercice->id,
            'expression_besoin_id' => $besoin->id,
            'type' => 'simple',
            'objet' => 'Lié',
            'montant_total' => 100,
            'statut' => 'brouillon',
            'cree_par' => $admin->id,
        ]);

        $this->actingAs($admin)->from('/besoins')->delete(route('besoins.destroy', $besoin))
            ->assertRedirect()
            ->assertSessionHas('erreur');
        $this->assertDatabaseHas('expressions_besoin', ['id' => $besoin->id]);
    }

    public function test_engagement_hors_pap_via_api_sans_activite(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-HPENG', 4000);
        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-HPENG',
            'exercice_id' => $this->exercice->id,
            'categorie' => 'hors_pap',
            'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $gestionnaire->id,
            'objet' => 'Fonctionnement hors PAP',
            'justification' => 'Hors programmation',
            'montant_estime' => 90,
            'statut' => 'valide',
        ]);

        $this->actingAs($gestionnaire)->postJson(route('api.engagements.store'), [
            'exercice_id' => $this->exercice->id,
            'expression_besoin_id' => $besoin->id,
            'type' => 'simple',
            'objet' => 'ENG hors PAP',
            'imputations' => [
                ['ligne_budgetaire_id' => $ligne->id, 'montant' => 90],
            ],
        ])->assertCreated()->assertJsonPath('code', 'cree');

        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
        $this->assertNull(Engagement::where('expression_besoin_id', $besoin->id)->value('activite_id'));
        $this->assertSame($activite->id, $ligne->activite_id);
    }

    public function test_initiateur_eb_ne_peut_pas_viser_son_propre_dossier(): void
    {
        $chef = $this->user('chef_service');
        $chef->assignRole('directeur');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-INI', 2000);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Initiateur SoD',
            'montant_estime' => 40,
        ])->assertCreated();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertOk();
        $this->actingAs($chef)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'transition_invalide']);
    }

    public function test_un_second_engagement_sur_le_meme_besoin_est_refuse(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-UNIQ', 3000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 55, 'EB-UNIQ');

        $payload = [
            'exercice_id' => $this->exercice->id,
            'expression_besoin_id' => $besoin->id,
            'type' => 'simple',
            'objet' => 'Premier ENG',
            'imputations' => [['ligne_budgetaire_id' => $ligne->id, 'montant' => 55]],
        ];

        $this->actingAs($gestionnaire)->postJson(route('api.engagements.store'), $payload)->assertCreated();
        $this->actingAs($gestionnaire)->postJson(route('api.engagements.store'), $payload)->assertStatus(422);
        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
    }

    public function test_fiche_eb_pdf_et_verification_interne(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-QR', 1000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $chef, 30, 'EB-QR');

        $bin = app(\App\Services\DocumentPdf::class)->besoin($besoin)->output();
        $this->assertNotEmpty($bin);

        $this->actingAs($chef)->get(route('depense.verifier', ['type' => 'besoin', 'id' => $besoin->id]))
            ->assertOk()
            ->assertSee('Vérification interne', false)
            ->assertSee($besoin->numero, false);

        $this->actingAs($chef)->get(route('api.besoins.pdf', $besoin))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_eb_pap_appui_suit_directeur_puis_sg(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $sg = $this->user('secretaire_general');
        $structureSg = \App\Models\Structure::query()->firstWhere('code', 'SG');
        $this->assertNotNull($structureSg);
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-APP', 4000);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'structure_id' => $structureSg->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Besoin PAP direction d’appui',
            'montant_estime' => 70,
        ])->assertCreated();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))
            ->assertOk()
            ->assertJsonPath('besoin.workflow.code', 'BESOIN_PAP_APPUI');

        $this->actingAs($directeur)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])->assertOk();
        $this->actingAs($sg)->postJson(route('api.besoins.decisions', $besoin->fresh()), ['decision' => 'valider'])
            ->assertOk()
            ->assertJsonPath('besoin.statut', 'valide');
        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
    }

    public function test_eb_refusee_si_exercice_clos(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-CLX', 1000);
        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Avant clôture',
            'montant_estime' => 20,
        ])->assertCreated();
        $besoin = ExpressionBesoin::latest('id')->first();
        $this->exercice->update(['statut' => 'clos']);

        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertStatus(422);
        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Après clôture',
            'montant_estime' => 20,
        ])->assertStatus(422);
        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);
    }

    public function test_convertir_est_idempotent(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-CONV', 2000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 45, 'EB-CONV');
        $headers = ['Idempotency-Key' => 'besoin-convertir-1'];

        $premier = $this->actingAs($gestionnaire)
            ->postJson(route('api.besoins.convertir', $besoin), [], $headers)
            ->assertOk();
        $this->actingAs($gestionnaire)
            ->postJson(route('api.besoins.convertir', $besoin), [], $headers)
            ->assertOk()
            ->assertJsonPath('code', 'converti');

        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
        $this->assertNotEmpty($premier->json('engagement_id') ?? $premier->json('besoin.engagement_id'));
    }

    public function test_panne_notification_n_annule_pas_la_soumission_eb(): void
    {
        $this->user('gestionnaire_budgetaire');
        $this->mock(\App\Services\Notifications\NotificationDispatcher::class, function ($mock) {
            $mock->shouldReceive('envoyer')->andThrow(new \RuntimeException('Canal indisponible'));
        });
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-NOTIF', 1500);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Soumission malgré panne mail',
            'montant_estime' => 25,
        ])->assertCreated();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))
            ->assertOk()
            ->assertJsonPath('code', 'soumis');
        $this->assertSame('soumis', $besoin->fresh()->statut);
        $this->assertDatabaseHas('notification_logs', [
            'type_code' => 'depense.besoin_soumis',
            'statut' => 'echec_reprise',
        ]);
        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);
    }

    public function test_eb_soumis_n_est_pas_modifiable_hors_retour(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-SOUM', 1200);
        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Déjà soumis',
            'montant_estime' => 18,
        ])->assertCreated();
        $besoin = ExpressionBesoin::latest('id')->first();
        $this->actingAs($chef)->postJson(route('api.besoins.soumettre', $besoin))->assertOk();

        $this->actingAs($chef)->putJson(route('api.besoins.update', $besoin->fresh()), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'Modification interdite',
            'montant_estime' => 18,
        ])->assertForbidden();
        $this->assertSame('Déjà soumis', $besoin->fresh()->objet);
        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);
    }

    public function test_if_match_refuse_une_soumission_eb_perimee(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-IFM', 900);
        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'If-Match EB',
            'montant_estime' => 12,
        ])->assertCreated();
        $besoin = ExpressionBesoin::latest('id')->first();

        $this->actingAs($chef)
            ->withHeaders(['If-Match' => '2000-01-01T00:00:00+00:00'])
            ->postJson(route('api.besoins.soumettre', $besoin))
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');
        $this->assertSame('brouillon', $besoin->fresh()->statut);
        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);
    }

    public function test_verification_qr_exige_une_session(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-QR2', 800);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $chef, 10, 'EB-QR2');

        $this->get(route('depense.verifier', ['type' => 'besoin', 'id' => $besoin->id]))
            ->assertRedirect();
    }

    public function test_piece_du_dossier_et_verification_api_session(): void
    {
        Storage::fake('local');
        $chef = $this->user('chef_service');
        $lecteur = $this->user('lecteur');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-PJ', 1200);

        $this->actingAs($chef)->postJson(route('api.besoins.store'), [
            'exercice_id' => $this->exercice->id,
            'categorie' => 'pap',
            'activite_id' => $activite->id,
            'objet' => 'PJ dossier',
            'montant_estime' => 40,
        ])->assertCreated();
        $besoin = ExpressionBesoin::latest('id')->first();

        $this->actingAs($lecteur)->post(route('api.pieces.store', ['type' => 'besoin', 'id' => $besoin->id]), [
            'fichier' => UploadedFile::fake()->create('lecture.pdf', 20, 'application/pdf'),
        ])->assertForbidden();

        $this->actingAs($chef)->post(route('api.pieces.store', ['type' => 'besoin', 'id' => $besoin->id]), [
            'fichier' => UploadedFile::fake()->create('devis.pdf', 20, 'application/pdf'),
            'categorie' => 'devis',
        ])->assertCreated()->assertJsonPath('code', 'piece_ajoutee');

        $liste = $this->actingAs($chef)->getJson(route('api.pieces.index', ['type' => 'besoin', 'id' => $besoin->id]))
            ->assertOk()
            ->assertJsonPath('peut_ajouter', true)
            ->assertJsonPath('data.0.nom', 'devis.pdf');
        $pieceId = (int) $liste->json('data.0.id');

        $this->actingAs($chef)->get(route('api.pieces.fichier', $pieceId))->assertOk();
        $this->actingAs($chef)->getJson(route('api.besoins.show', $besoin))
            ->assertOk()
            ->assertJsonPath('data.verification_url', '/api/v1/verification/besoin/'.$besoin->id);

        $this->actingAs($chef)->getJson(route('api.verification.show', ['type' => 'besoin', 'id' => $besoin->id]))
            ->assertOk()
            ->assertJsonPath('type', 'besoin')
            ->assertJsonPath('numero', $besoin->numero)
            ->assertJsonPath('page', '/verification/besoin/'.$besoin->id);
        $this->actingAs($lecteur)->getJson(route('api.verification.show', ['type' => 'besoin', 'id' => $besoin->id]))
            ->assertOk();
        $this->assertSame($ligne->id, $activite->fresh()->ligne_budgetaire_id);
    }

    public function test_qr_pdf_pointe_vers_la_page_spa_authentifiee(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-QR2', 1000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $chef, 30, 'EB-QR2');
        $url = \App\Services\DocumentPdf::urlVerification('besoin', $besoin);
        $this->assertStringContainsString('/verification/besoin/'.$besoin->id, $url);
        $this->assertStringNotContainsString('/api/v1/', $url);
    }

    public function test_dossier_unifie_api_session(): void
    {
        $chef = $this->user('chef_service');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-EB-DOS', 1000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $chef, 40, 'EB-DOS');

        $this->actingAs($chef)->getJson(route('api.dossiers.besoin', $besoin))
            ->assertOk()
            ->assertJsonPath('data.besoin.numero', $besoin->numero)
            ->assertJsonPath('data.timeline.0.type', 'besoin')
            ->assertJsonPath('data.timeline.0.montant', '40.00');
    }

    public function test_consolidation_besoins_api_session(): void
    {
        $directeur = $this->user('directeur');
        [$activite] = $this->activiteEtLigne($this->exercice, 'LB-EB-CONS', 2000);

        $hub = $this->actingAs($directeur)->getJson(route('api.besoins.consolidation', ['exercice' => $this->exercice->id]))
            ->assertOk();
        $this->assertIsString($hub->json('data.synthese.montant_total'));

        $this->actingAs($directeur)->postJson(route('api.besoins.consolidation.store'), [
            'exercice_id' => $this->exercice->id,
        ])->assertCreated()->assertJsonPath('code', 'consolide');
    }
}
