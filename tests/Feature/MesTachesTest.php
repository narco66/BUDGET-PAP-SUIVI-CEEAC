<?php

namespace Tests\Feature;

use App\Models\ChaineDepenseParametre;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Models\WorkflowTacheEvenement;
use App\Services\Institution\InstitutionGouvernanceService;
use App\Services\Workflow\TacheActionService;
use App\Services\Workflow\TacheQueryService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Module « Mes Tâches » : cycle de vie, habilitations, concurrence, intégration chaîne de dépense. */
class MesTachesTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function besoinSoumis(User $demandeur, string $circuit = 'BESOIN_PAP', float $montant = 500): ExpressionBesoin
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-MT-'.uniqid(), 100_000);
        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-MT-'.uniqid(), 'exercice_id' => $this->exercice->id, 'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id, 'demandeur_id' => $demandeur->id, 'objet' => 'Besoin Mes Tâches',
            'montant_estime' => $montant, 'priorite' => 'moyenne', 'statut' => 'brouillon',
        ]);
        $this->workflow->demarrer($besoin, $circuit, $demandeur);

        return $besoin->fresh();
    }

    private function tacheOuverte(ExpressionBesoin|Model $objet): ?WorkflowTache
    {
        return WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->id)->ouvertes()->first();
    }

    public function test_rec01_soumission_eb_cree_une_tache_pour_le_directeur(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);

        $this->assertNotNull($tache);
        $this->assertSame('directeur', $tache->role);
        $this->assertSame('a_traiter', $tache->statut);
        $this->assertSame('EB', $tache->module);
        $this->assertMatchesRegularExpression('/^TACHE-\d{4}-\d{6}$/', $tache->reference);
        // Garde-fou : la référence provisoire doit tenir dans la colonne (30) — SQLite ne le vérifie pas.
        $this->assertLessThanOrEqual(30, strlen('TMP-'.\Illuminate\Support\Str::random(24)));
        $this->assertStringContainsString("'TMP-'.Str::random(24)", file_get_contents(app_path('Services/Workflow/TacheWorkflowService.php')));
        $this->assertTrue(WorkflowTacheEvenement::where('workflow_tache_id', $tache->id)->where('type', 'creation')->exists());
    }

    public function test_rec02_rec13_validation_clot_la_tache_et_affecte_l_etape_suivante(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $directeur = $this->user('directeur');
        $tache = $this->tacheOuverte($besoin);

        app(TacheActionService::class)->decider($tache, $directeur, ['decision' => 'valider']);

        $tache->refresh();
        $this->assertSame('terminee', $tache->statut);
        $this->assertSame('valide', $tache->decision);
        $this->assertSame($directeur->id, (int) $tache->termine_par);
        $suivante = $this->tacheOuverte($besoin);
        $this->assertSame('commissaire', $suivante->role);
        $this->assertCount(0, app(TacheQueryService::class)->collection($directeur, 'mes'));
        $this->assertCount(1, app(TacheQueryService::class)->collection($directeur, 'traitees'));
    }

    public function test_renvoi_depuis_l_etape_2_ne_bloque_plus_le_circuit(): void
    {
        $demandeur = $this->user('chef_service');
        $besoin = $this->besoinSoumis($demandeur);
        $directeur = $this->user('directeur');
        $commissaire = $this->user('commissaire');
        $actions = app(TacheActionService::class);

        $actions->decider($this->tacheOuverte($besoin), $directeur, ['decision' => 'valider']);
        $etape2 = $this->tacheOuverte($besoin);
        $actions->decider($etape2, $commissaire, ['decision' => 'renvoyer', 'observation' => 'Compléter les devis']);

        // Le retour pour correction revient au demandeur : tâche de correction, aucune validation en attente.
        $this->assertSame('renvoye', $etape2->fresh()->decision);
        $retour = $this->tacheOuverte($besoin);
        $this->assertSame('preparation', $retour->nature, 'Le dossier revient au demandeur pour correction.');
        $this->assertSame('eb_corriger', $retour->action_code);
        $this->assertSame('a_corriger', $besoin->fresh()->statut);

        // Resoumission : le circuit reprend au niveau qui a renvoyé (le commissaire), sans repasser par le directeur.
        $this->workflow->reprendreApresCorrection($besoin->fresh(), $demandeur);
        $this->assertSame('terminee', $retour->fresh()->statut);
        $reprise = $this->tacheOuverte($besoin);
        $this->assertNotNull($reprise, 'L\'étape 2 doit redevenir traitable (blocage corrigé).');
        $this->assertSame('commissaire', $reprise->role);

        $actions->decider($reprise, $commissaire, ['decision' => 'valider']);
        $this->assertSame('valide', $besoin->fresh()->statut);
        // Historique complet des validations : validation, renvoi, validation finale ; la correction est tracée à part.
        $terminees = WorkflowTache::where('objet_id', $besoin->id)->where('objet_type', $besoin->getMorphClass())->where('statut', 'terminee')->orderBy('id');
        $this->assertSame(['valide', 'renvoye', 'valide'], (clone $terminees)->where('nature', 'validation')->pluck('decision')->all());
        $this->assertSame(['traitee'], (clone $terminees)->where('nature', 'preparation')->pluck('decision')->all());
    }

    public function test_rec10_utilisateur_non_habilite_ne_peut_ni_voir_ni_decider(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);
        $sansRole = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $comptable = $this->user('comptable');

        // Sans aucun droit : ni consultation ni décision, même en connaissant l'identifiant.
        $this->actingAs($sansRole)->getJson(route('api.taches.show', $tache))->assertForbidden();
        $this->actingAs($sansRole)->postJson(route('api.taches.decisions', $tache), ['decision' => 'valider'])->assertForbidden();

        // Consultation de supervision possible (alias workflow.audit), mais ni décision ni réaffectation.
        $this->assertFalse(app(TacheQueryService::class)->peutTraiter($tache, $comptable));
        $this->actingAs($comptable)->postJson(route('api.taches.decisions', $tache), ['decision' => 'valider'])
            ->assertStatus(422)->assertJsonPath('code', 'action_refusee');
        $this->assertNotContains('reaffecter', app(TacheActionService::class)->actionsDisponibles($tache, $comptable));
        $this->assertSame('a_traiter', $tache->fresh()->statut);
    }

    public function test_l_administrateur_n_est_pas_place_dans_la_file_de_travail(): void
    {
        $this->besoinSoumis($this->user('chef_service'));

        $this->assertCount(0, app(TacheQueryService::class)->collection($this->user('administrateur'), 'mes'));
    }

    public function test_l_initiateur_titulaire_du_role_ne_voit_pas_son_propre_dossier_a_traiter(): void
    {
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($directeur);

        $tache = $this->tacheOuverte($besoin);
        $this->assertFalse(app(TacheQueryService::class)->peutTraiter($tache, $directeur));
        $this->assertStringContainsString('Séparation des fonctions', app(TacheQueryService::class)->eligibilite($tache, $directeur)['motif']);
    }

    public function test_rec12_deux_decisions_sur_la_meme_tache_une_seule_acceptee(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);
        $versionVue = $tache->version;
        $d1 = $this->user('directeur');
        $d2 = $this->user('directeur');

        app(TacheActionService::class)->decider($tache, $d1, ['decision' => 'valider'], $versionVue);

        $this->expectException(RuntimeException::class);
        app(TacheActionService::class)->decider($tache, $d2, ['decision' => 'valider'], $versionVue);
    }

    public function test_decision_api_obsolete_renvoie_409_et_idempotence(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);
        $directeur = $this->user('directeur');

        $this->actingAs($directeur)->postJson(route('api.taches.decisions', $tache), ['decision' => 'valider', 'version' => $tache->version + 7])
            ->assertStatus(409)->assertJsonPath('code', 'conflict');

        $entetes = ['Idempotency-Key' => 'cle-mt-1'];
        $r1 = $this->actingAs($directeur)->withHeaders($entetes)->postJson(route('api.taches.decisions', $tache), ['decision' => 'valider']);
        $r1->assertOk();
        $r2 = $this->actingAs($directeur)->withHeaders($entetes)->postJson(route('api.taches.decisions', $tache), ['decision' => 'valider']);
        $r2->assertOk();
        $this->assertSame(1, WorkflowTache::where('objet_id', $besoin->id)->where('decision', 'valide')->count());
    }

    public function test_prise_en_charge_exclusive_et_liberation(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);
        $d1 = $this->user('directeur');
        $d2 = $this->user('directeur');
        $actions = app(TacheActionService::class);

        $actions->prendreEnCharge($tache, $d1, $tache->version);
        $this->assertSame('en_cours', $tache->fresh()->statut);
        $this->assertFalse(app(TacheQueryService::class)->peutTraiter($tache->fresh(), $d2));

        try {
            $actions->decider($tache->fresh(), $d2, ['decision' => 'valider']);
            $this->fail('Un autre agent ne peut pas décider une tâche prise en charge.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('prise en charge', $e->getMessage());
        }

        $actions->liberer($tache->fresh(), $d1);
        $this->assertSame('a_traiter', $tache->fresh()->statut);
        $this->assertTrue(app(TacheQueryService::class)->peutTraiter($tache->fresh(), $d2));
        $this->assertSame(['creation', 'prise_en_charge', 'liberation'], WorkflowTacheEvenement::where('workflow_tache_id', $tache->id)->orderBy('id')->pluck('type')->all());
    }

    public function test_suspension_bloque_la_decision_jusqu_a_la_reprise(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);
        $d = $this->user('directeur');
        $actions = app(TacheActionService::class);

        $actions->suspendre($tache, $d, 'Attente du devis complémentaire');
        try {
            $actions->decider($tache->fresh(), $d, ['decision' => 'valider']);
            $this->fail('Décision interdite sur une tâche suspendue.');
        } catch (RuntimeException) {
        }
        $actions->reprendre($tache->fresh(), $d);
        $actions->decider($tache->fresh(), $d, ['decision' => 'valider']);
        $this->assertSame('terminee', $tache->fresh()->statut);
    }

    public function test_rec11_delegation_valide_autorise_et_delegation_expiree_refuse(): void
    {
        $sg = $this->user('secretaire_general');
        $structureSg = Structure::query()->firstOrNew(['code' => 'SG']);
        $structureSg->fill(['libelle' => 'Secrétariat général', 'sigle' => 'SG', 'type' => 'secretariat', 'actif' => true, 'responsable_user_id' => $sg->id])->save();
        $delegue = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');

        $besoin = $this->besoinSoumis($chef, 'BESOIN_PAP_APPUI');
        $besoin->update(['structure_id' => $structureSg->id]);
        app(TacheActionService::class)->decider($this->tacheOuverte($besoin), $directeur, ['decision' => 'valider']);
        $tacheSg = $this->tacheOuverte($besoin);
        $this->assertSame('secretaire_general', $tacheSg->role);

        $gouv = app(InstitutionGouvernanceService::class);
        $gouv->creerDelegation(['structure_id' => $structureSg->id, 'delegue_par' => $sg->id, 'delegue_a' => $delegue->id,
            'date_debut' => now()->subDays(10)->toDateString(), 'date_fin' => now()->subDay()->toDateString(), 'motif' => 'Expirée']);
        $this->assertFalse(app(TacheQueryService::class)->peutTraiter($tacheSg->fresh(), $delegue), 'Délégation expirée : refus.');

        $gouv->creerDelegation(['structure_id' => $structureSg->id, 'delegue_par' => $sg->id, 'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(), 'motif' => 'Mission du SG']);
        $requetes = app(TacheQueryService::class);
        $this->assertTrue($requetes->peutTraiter($tacheSg->fresh(), $delegue));
        $this->assertCount(1, $requetes->collection($delegue, 'deleguees'));

        app(TacheActionService::class)->decider($tacheSg->fresh(), $delegue, ['decision' => 'valider']);
        $decision = WorkflowTacheEvenement::where('workflow_tache_id', $tacheSg->id)->where('type', 'decision')->first();
        $this->assertSame($delegue->id, (int) $decision->user_id);
        $this->assertSame($sg->id, (int) $decision->pour_compte_de, 'Titulaire et exécutant réel distincts.');
    }

    public function test_rec16_rg11_notification_lue_sans_effet_sur_la_tache(): void
    {
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);

        $notification = $directeur->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('/mes-taches/'.$tache->id, (string) data_get($notification->data, 'url'));

        $notification->markAsRead();
        $this->assertSame('a_traiter', $tache->fresh()->statut);
    }

    public function test_rec07_rec08_ordonnancement_affecte_selon_le_seuil(): void
    {
        ChaineDepenseParametre::query()->updateOrCreate(['cle' => 'ordonnancement.seuil_xaf'], ['valeur' => '5000000.00', 'version' => 1, 'juridiquement_valide' => false]);
        $gest = $this->user('gestionnaire_budgetaire');
        $seuils = app(\App\Services\Depense\SeuilOrdonnancementService::class);

        foreach ([[5_000_000, 'secretaire_general'], [6_000_000, 'president']] as [$montant, $role]) {
            [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-ORD-'.$montant, 20_000_000);
            $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gest, $montant, 'EB-ORD-'.$montant);
            $eng = Engagement::create(['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'numero' => 'ENG-ORD-'.$montant, 'type' => 'simple', 'objet' => 'ORD', 'montant_total' => $montant, 'statut' => 'vise', 'credits_reserves' => true]);
            $liq = Liquidation::create(['numero' => 'LIQ-ORD-'.$montant, 'engagement_id' => $eng->id, 'montant' => $montant, 'statut' => 'valide', 'compteurs_appliques' => true]);
            $ord = Ordonnancement::create(['numero' => 'ORD-'.$montant, 'liquidation_id' => $liq->id, 'montant' => $montant, 'statut' => 'brouillon', 'cree_par' => $gest->id]);
            $this->workflow->demarrer($ord, $seuils->circuit($ord), $gest);

            $this->assertSame($role, $this->tacheOuverte($ord)->role, "ORD de {$montant} XAF");
            $this->assertSame('ORD', $this->tacheOuverte($ord)->module);
        }
    }

    public function test_v1_controle_du_rib_non_contournable_par_l_ecran_blade(): void
    {
        $gest = $this->user('gestionnaire_budgetaire');
        $agent = $this->user('agent_comptable');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-RIB', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gest, 300, 'EB-RIB');
        $eng = Engagement::create(['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'numero' => 'ENG-RIB', 'type' => 'simple', 'objet' => 'RIB', 'montant_total' => 300, 'statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-RIB', 'engagement_id' => $eng->id, 'montant' => 300, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-RIB', 'liquidation_id' => $liq->id, 'montant' => 300, 'statut' => 'valide', 'compteurs_appliques' => true]);
        // Paiement antérieur sur un autre RIB : le nouveau RIB constitue un changement à contrôler.
        Paiement::create(['numero' => 'PAY-RIB-0', 'ordonnancement_id' => $ord->id, 'montant' => 100, 'mode_paiement' => 'virement', 'rib' => 'CM21 1111', 'statut' => 'rejete', 'cree_par' => $gest->id]);
        $pay = Paiement::create(['numero' => 'PAY-RIB', 'ordonnancement_id' => $ord->id, 'montant' => 300, 'mode_paiement' => 'virement', 'rib' => 'CM21 0000', 'statut' => 'brouillon', 'cree_par' => $gest->id]);
        app(\App\Services\Depense\RibControleService::class)->enregistrerSaisie($pay->fresh(), $agent);
        $this->workflow->demarrer($pay, 'PAIEMENT', $gest);
        $this->workflow->valider($pay->fresh()->workflowActif, $this->user('comptable'));

        $this->actingAs($agent)->post(route('depense.agir', ['paiement', $pay->id]), ['decision' => 'valider'])->assertSessionHas('erreur');
        $this->assertNotSame('valide', $pay->fresh()->statut, 'Le saisisseur du RIB ne peut pas autoriser le décaissement, y compris via Blade.');
    }

    public function test_rec09_paiement_partiel_reliquat_affiche_sur_la_fiche(): void
    {
        $gest = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-PP-MT', 20_000_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gest, 10_000_000, 'EB-PP-MT');
        $eng = Engagement::create(['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'numero' => 'ENG-PP-MT', 'type' => 'simple', 'objet' => 'PP', 'montant_total' => 10_000_000, 'statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-PP-MT', 'engagement_id' => $eng->id, 'montant' => 10_000_000, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-PP-MT', 'liquidation_id' => $liq->id, 'montant' => 10_000_000, 'statut' => 'valide', 'compteurs_appliques' => true]);
        Paiement::create(['numero' => 'PAY-PP-1', 'ordonnancement_id' => $ord->id, 'montant' => 4_000_000, 'mode_paiement' => 'cheque', 'statut' => 'valide', 'cree_par' => $gest->id]);
        $pay2 = Paiement::create(['numero' => 'PAY-PP-2', 'ordonnancement_id' => $ord->id, 'montant' => 6_000_000, 'mode_paiement' => 'cheque', 'statut' => 'brouillon', 'cree_par' => $gest->id]);
        $this->workflow->demarrer($pay2, 'PAIEMENT', $gest);
        $tache = $this->tacheOuverte($pay2);
        $comptable = $this->user('comptable');

        $this->actingAs($comptable)->get(route('taches.show', $tache))->assertOk()
            ->assertSee('Reste à payer')->assertSee('6 000 000');
    }

    public function test_rg15_statistiques_et_export_limites_au_perimetre(): void
    {
        $this->besoinSoumis($this->user('chef_service'));
        $directeur = $this->user('directeur');
        $lecteur = $this->user('lecteur');

        $this->assertSame(1, app(TacheQueryService::class)->statistiques($directeur)['a_traiter']);
        $this->assertSame(0, app(TacheQueryService::class)->statistiques($lecteur)['a_traiter']);

        $csv = $this->actingAs($lecteur)->get(route('taches.export'))->streamedContent();
        $this->assertStringNotContainsString('TACHE-', $csv);
        $csv = $this->actingAs($directeur)->get(route('taches.export'))->streamedContent();
        $this->assertStringContainsString('TACHE-', $csv);
    }

    public function test_pages_blade_et_api_listes(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $directeur = $this->user('directeur');
        $tache = $this->tacheOuverte($besoin);

        $this->actingAs($directeur)->get(route('taches.index'))->assertOk()->assertSee($tache->reference);
        $this->actingAs($directeur)->get(route('taches.show', $tache))->assertOk()->assertSee('Étape 1');
        $this->actingAs($directeur)->getJson(route('api.taches.vue', 'my'))->assertOk()
            ->assertJsonPath('data.0.reference', $tache->reference)
            ->assertJsonPath('data.0.available_actions', fn ($a) => in_array('valider', $a, true) && in_array('prendre_en_charge', $a, true));
        $this->actingAs($directeur)->getJson(route('api.taches.stats'))->assertOk()->assertJsonPath('data.a_traiter', 1);
        $this->actingAs(User::factory()->create(['actif' => true, 'statut' => 'actif']))->getJson(route('api.taches.supervision'))->assertForbidden();
        $this->actingAs($this->user('secretaire_general'))->getJson(route('api.taches.supervision'))->assertOk()->assertJsonPath('data.ouvertes', 1);
    }

    public function test_commande_de_reprise_idempotente(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        WorkflowTache::query()->delete();

        $this->artisan('taches:synchroniser')->assertSuccessful();
        $this->artisan('taches:synchroniser')->assertSuccessful();
        $this->assertSame(1, WorkflowTache::where('objet_id', $besoin->id)->ouvertes()->count());
    }
    public function test_dossier_traite_disparait_des_notifications_mes_taches_et_corbeille(): void
    {
        $directeur = $this->user('directeur');
        $autreDirecteur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = $this->tacheOuverte($besoin);
        $this->assertSame(1, $directeur->unreadNotifications()->count());
        $this->assertSame(1, $autreDirecteur->unreadNotifications()->count());

        app(TacheActionService::class)->decider($tache, $directeur, ['decision' => 'valider']);

        // Notifications : lues et marquées traitées chez l'acteur et chez les autres destinataires.
        $this->assertSame(0, $directeur->unreadNotifications()->count());
        $this->assertSame(0, $autreDirecteur->unreadNotifications()->count());
        $this->assertTrue((bool) data_get($directeur->notifications()->first()->data, 'meta.tache_traitee'));
        $this->assertSame($directeur->name, data_get($directeur->notifications()->first()->data, 'meta.traitee_par'));
        // Mes tâches et corbeille de validation : le dossier n'est plus « à traiter » pour l'acteur.
        $this->assertCount(0, app(TacheQueryService::class)->collection($directeur, 'mes'));
        $this->assertCount(0, app(\App\Services\Workflow\WorkflowCorbeilleService::class)->tachesPour($directeur));
    }

    public function test_acteur_detenant_aussi_le_role_suivant_ne_retrouve_pas_le_dossier_dans_la_corbeille(): void
    {
        // Séparation des fonctions : celui qui a validé l'étape 1 ne valide pas l'étape suivante.
        $cumul = $this->user('directeur')->assignRole('commissaire');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        app(TacheActionService::class)->decider($this->tacheOuverte($besoin), $cumul, ['decision' => 'valider']);

        $this->assertSame('commissaire', $this->tacheOuverte($besoin)->role);
        $this->assertCount(0, app(\App\Services\Workflow\WorkflowCorbeilleService::class)->tachesPour($cumul));
        $this->assertCount(0, app(TacheQueryService::class)->collection($cumul, 'mes'));
    }
}
