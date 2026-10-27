<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\AuditExport;
use App\Models\ChaineDepenseParametre;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\JournalAudit;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\Audit\AuditArchivageService;
use App\Services\Audit\AuditIntegriteService;
use App\Services\Institution\InstitutionGouvernanceService;
use App\Services\UserService;
use App\Services\Workflow\TacheActionService;
use App\Services\WorkflowService;
use App\Support\AuditEmpreinte;
use App\Support\AuditLogger;
use App\Support\Horodatage;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Module « Journalisation, Traçabilité et Horodatage » : recette REC-J01 à REC-J25 et règles JNL. */
class JournalisationTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'audit.integrite.delai_scellement_secondes' => 0]);
        Storage::fake('local');
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role, array $attributs = []): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'] + $attributs)->assignRole($role);
    }

    private function besoinSoumis(User $demandeur, float $montant = 500, string $circuit = 'BESOIN_PAP'): ExpressionBesoin
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-JNL-'.uniqid(), 100_000);
        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-JNL-'.uniqid(), 'exercice_id' => $this->exercice->id, 'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id, 'demandeur_id' => $demandeur->id, 'objet' => 'Besoin journalisation',
            'montant_estime' => $montant, 'priorite' => 'moyenne', 'statut' => 'brouillon',
        ]);
        $this->workflow->demarrer($besoin, $circuit, $demandeur);

        return $besoin->fresh();
    }

    private function tache(Model $objet): ?WorkflowTache
    {
        return WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->id)->ouvertes()->first();
    }

    private function dernier(string $action, ?Model $objet = null): ?JournalAudit
    {
        return JournalAudit::query()->where('action', $action)
            ->when($objet, fn ($q) => $q->where('auditable_type', $objet::class)->where('auditable_id', $objet->getKey()))
            ->latest('id')->first();
    }

    /** Chaîne EB → ENG → LIQ → ORD avec un ORD au-dessus du seuil. */
    private function ordonnancement(User $gest, float $montant, string $suffixe): Ordonnancement
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-'.$suffixe, 50_000_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gest, $montant, 'EB-'.$suffixe);
        $eng = Engagement::create(['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'numero' => 'ENG-'.$suffixe, 'type' => 'simple', 'objet' => 'JNL', 'montant_total' => $montant, 'statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-'.$suffixe, 'engagement_id' => $eng->id, 'montant' => $montant, 'statut' => 'valide', 'compteurs_appliques' => true]);

        return Ordonnancement::create(['numero' => 'ORD-'.$suffixe, 'liquidation_id' => $liq->id, 'montant' => $montant, 'statut' => 'brouillon', 'cree_par' => $gest->id,
            'seuil_xaf' => '5000000.00', 'seuil_version' => 3, 'assiette_seuil' => 'net', 'ordonnateur_role' => $montant > 5_000_000 ? 'president' : 'secretaire_general']);
    }

    public function test_rec_j01_evenement_classe_horodate_en_utc_et_empreinte(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));

        $creation = $this->dernier('created', $besoin);
        $this->assertNotNull($creation, 'REC-J01 : la création de l\'EB produit un événement.');
        $this->assertSame('EB', $creation->module);
        $this->assertSame('EB.CREATED', $creation->code_evenement);
        $this->assertSame('metier', $creation->categorie);
        $this->assertSame('EB-'.$besoin->id, $creation->correlation_id);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', AuditEmpreinte::instant($creation->getRawOriginal('occurred_at')));
        $this->assertTrue($creation->empreinteValide(), 'Empreinte SHA-256 canonique vérifiable.');
        $this->assertNotNull($this->dernier('workflow_demarre', $besoin));
        $this->assertSame('EB.SOUMISSION', $this->dernier('workflow_demarre', $besoin)->code_evenement);
    }

    public function test_affichage_dans_le_fuseau_institutionnel_sans_alterer_l_instant(): void
    {
        $this->assertSame('25/10/2026 09:42:18.325', Horodatage::local('2026-10-25T08:42:18.325Z', 'd/m/Y H:i:s.v'));
        $this->assertSame('2026-10-25T08:42:18.325000Z', Horodatage::utc('2026-10-25 09:42:18.325+01:00'));
    }

    public function test_rec_j02_changement_de_montant_conserve_avant_apres(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'), 500);
        $besoin->update(['montant_estime' => 750]);

        $j = $this->dernier('updated', $besoin);
        $this->assertEquals(500, (float) $j->avant['montant_estime']);
        $this->assertEquals(750, (float) $j->apres['montant_estime']);
    }

    public function test_rec_j03_j14_validation_eb_auteur_etape_et_pas_de_double_decision(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $directeur = $this->user('directeur');
        $tache = $this->tache($besoin);

        $this->actingAs($directeur);
        app(TacheActionService::class)->decider($tache, $directeur, ['decision' => 'valider', 'observation' => 'Conforme au PAP']);
        try {
            app(TacheActionService::class)->decider($tache->fresh(), $directeur, ['decision' => 'valider']);
        } catch (\Throwable) {
        }

        $decisions = JournalAudit::query()->where('action', 'workflow_valide')->where('auditable_id', $besoin->id)->get();
        $this->assertCount(1, $decisions, 'REC-J14 : une seule décision journalisée.');
        $d = $decisions->first();
        $this->assertSame($directeur->id, (int) $d->user_id);
        $this->assertStringContainsString('directeur', (string) $d->role);
        $this->assertSame('Conforme au PAP', $d->motif);
        $this->assertSame(1, $d->apres['etape']);
        $this->assertSame('directeur', $d->apres['role_competent']);
        $this->assertSame($tache->id, (int) $d->workflow_tache_id);
        $this->assertNotNull($d->workflow_instance_id);
    }

    public function test_rec_j07_ordonnancement_au_dessus_du_seuil_trace_seuil_version_et_autorite(): void
    {
        $gest = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement($gest, 6_000_000, 'J07');
        $this->workflow->demarrer($ord, app(\App\Services\Depense\SeuilOrdonnancementService::class)->circuit($ord), $gest);
        $president = $this->user('president');
        $this->actingAs($president);
        $this->workflow->valider($ord->fresh()->workflowActif, $president);

        $d = $this->dernier('workflow_valide', $ord);
        $this->assertEquals(5_000_000, (float) $d->apres['seuil_xaf']);
        $this->assertSame(3, $d->apres['seuil_version']);
        $this->assertSame('president', $d->apres['autorite_competente']);
        $this->assertSame('ORD', $d->module);
        $this->assertStringStartsWith('EB-', (string) $d->correlation_id, 'JNL-009 : ORD corrélé à l\'EB d\'origine.');
    }

    public function test_rec_j08_paiement_partiel_trace_distincte_et_reliquat(): void
    {
        $gest = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement($gest, 10_000_000, 'J08');
        $ord->update(['statut' => 'valide', 'compteurs_appliques' => true]);
        $pay = Paiement::create(['numero' => 'PAY-J08-1', 'ordonnancement_id' => $ord->id, 'montant' => 4_000_000, 'mode_paiement' => 'cheque', 'statut' => 'brouillon', 'cree_par' => $gest->id]);
        $this->workflow->demarrer($pay, 'PAIEMENT', $gest);
        $instance = $pay->fresh()->workflowActif;
        foreach ($instance->etapes()->orderBy('ordre')->get() as $etape) {
            $acteur = $this->user($etape->role);
            $this->actingAs($acteur);
            $this->workflow->valider($instance->fresh(), $acteur);
        }

        $d = $this->dernier('workflow_valide', $pay);
        $this->assertSame('4000000.00', $d->apres['montant_xaf']);
        $this->assertSame('10000000.00', $d->apres['montant_ordonnance_xaf']);
        $this->assertSame('6000000.00', $d->apres['reliquat_xaf'], 'Le premier paiement ne présente pas le dossier comme soldé.');
        $this->assertSame('EB-'.$ord->liquidation->engagement->expression_besoin_id, $d->correlation_id);
    }

    public function test_rec_j09_reaffectation_trace_ancien_et_nouvel_acteur(): void
    {
        $sg = $this->user('secretaire_general');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $d1 = $this->user('directeur');
        $d2 = $this->user('directeur');
        $actions = app(TacheActionService::class);
        $tache = $this->tache($besoin);
        $actions->prendreEnCharge($tache, $d1);
        $this->actingAs($sg);
        $actions->reaffecter($tache->fresh(), $sg, $d2, 'Absence de l\'agent');

        $j = $this->dernier('tache_reaffectation');
        $this->assertSame($d1->id, (int) $j->apres['de']);
        $this->assertSame($d2->id, (int) $j->apres['vers']);
        $this->assertSame('Absence de l\'agent', $j->motif);
        $this->assertSame($tache->id, (int) $j->workflow_tache_id);
    }

    public function test_rec_j10_decision_par_delegation_distingue_titulaire_et_executant(): void
    {
        $sg = $this->user('secretaire_general');
        $structureSg = Structure::query()->firstOrNew(['code' => 'SG']);
        $structureSg->fill(['libelle' => 'Secrétariat général', 'sigle' => 'SG', 'type' => 'secretariat', 'actif' => true, 'responsable_user_id' => $sg->id])->save();
        $delegue = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $besoin = $this->besoinSoumis($this->user('chef_service'), 500, 'BESOIN_PAP_APPUI');
        $besoin->update(['structure_id' => $structureSg->id]);
        $this->workflow->valider($besoin->fresh()->workflowActif, $this->user('directeur'));
        $tacheSg = $this->tache($besoin);
        $this->assertSame('secretaire_general', $tacheSg->role);
        app(InstitutionGouvernanceService::class)->creerDelegation(['structure_id' => $structureSg->id, 'delegue_par' => $sg->id, 'delegue_a' => $delegue->id,
            'date_debut' => now()->toDateString(), 'motif' => 'Mission du SG']);
        $this->assertNotNull($this->dernier('created', \App\Models\Delegation::query()->latest('id')->first()), 'La délégation elle-même est tracée.');

        $this->actingAs($delegue);
        app(TacheActionService::class)->decider($tacheSg->fresh(), $delegue, ['decision' => 'valider']);

        $d = $this->dernier('workflow_valide', $besoin);
        $this->assertSame($delegue->id, (int) $d->user_id, 'Auteur réel.');
        $this->assertSame($sg->id, (int) $d->pour_compte_de, 'Titulaire représenté.');
    }

    public function test_rec_j11_modification_des_roles_d_un_utilisateur_avant_apres(): void
    {
        $admin = $this->user('administrateur');
        $cible = $this->user('lecteur');
        $this->actingAs($admin);
        app(UserService::class)->synchroniserRoles($cible, ['lecteur', 'comptable'], $admin);

        $j = $this->dernier('utilisateur_roles_modifies', $cible);
        $this->assertSame(['lecteur'], $j->avant['roles']);
        $this->assertSame(['comptable', 'lecteur'], $j->apres['roles']);
        $this->assertSame('securite', $j->categorie);
    }

    public function test_rec_j12_j25_acces_interdit_journalise_et_refuse(): void
    {
        $lecteur = $this->user('lecteur');
        $this->actingAs($lecteur)->get(route('audit.integrite'))->assertForbidden();
        $this->actingAs($lecteur)->get(route('logs.connexions'))->assertForbidden();

        $refus = JournalAudit::query()->where('action', 'acces_refuse')->get();
        $this->assertEqualsCanonicalizing(['/audit/integrite', '/logs/connexions'], $refus->pluck('apres.chemin')->all());
        $j = $refus->first();
        $this->assertSame('securite', $j->categorie);
        $this->assertSame('refus', $j->resultat);
        $this->assertSame($lecteur->id, (int) $j->user_id);
    }

    public function test_echec_de_connexion_journalise_sans_secret(): void
    {
        $this->post(route('login'), ['email' => 'inconnu@ceeac.test', 'password' => 'mauvais-secret']);

        $j = $this->dernier('auth_echec');
        $this->assertSame('inconnu@ceeac.test', $j->user_nom);
        $this->assertSame('securite', $j->categorie);
        $this->assertStringNotContainsString('mauvais-secret', json_encode($j->getAttributes()));
    }

    public function test_rec_j13_parametre_de_seuil_modifie_conserve_valeurs_et_version(): void
    {
        $p = ChaineDepenseParametre::query()->updateOrCreate(['cle' => 'ordonnancement.seuil_xaf'], ['valeur' => '5000000.00', 'version' => 1, 'juridiquement_valide' => false]);
        $p->update(['valeur' => '7500000.00', 'version' => 2]);

        $j = $this->dernier('updated', $p);
        $this->assertSame('5000000.00', $j->avant['valeur']);
        $this->assertSame('7500000.00', $j->apres['valeur']);
        $this->assertEquals(1, $j->avant['version']);
        $this->assertSame('PARAMETRAGE', $j->module);
    }

    public function test_jnl006_journal_en_ajout_seul_pour_l_application(): void
    {
        $j = AuditLogger::evenement('essai');
        $this->expectException(LogicException::class);
        $j->update(['action' => 'falsifie']);
    }

    public function test_jnl006_suppression_refusee(): void
    {
        $j = AuditLogger::evenement('essai');
        $this->expectException(LogicException::class);
        $j->delete();
    }

    public function test_jnl011_aucun_secret_journalise(): void
    {
        $j = AuditLogger::evenement('essai', null, ['apres' => ['nom' => 'x', 'password' => 'p', 'api_token' => 't', 'config' => ['client_secret' => 's', 'mfa_secret' => 'm', 'url' => 'u']]]);

        $this->assertSame(['nom' => 'x', 'config' => ['url' => 'u']], $j->apres);
    }

    public function test_rec_j15_cloisonnement_propre_unite_etendu(): void
    {
        $lecteur = $this->user('lecteur');
        $chef = $this->user('directeur');
        $structure = Structure::create(['code' => 'DJNL', 'libelle' => 'Direction test', 'sigle' => 'DJNL', 'type' => 'direction', 'actif' => true, 'responsable_user_id' => $chef->id]);
        $agent = $this->user('gestionnaire_budgetaire', ['structure_id' => $structure->id]);
        $auditeur = $this->user('auditeur');

        $this->actingAs($agent);
        $evtAgent = AuditLogger::evenement('budget_essai');
        $this->actingAs($lecteur);
        $evtLecteur = AuditLogger::evenement('budget_essai');

        $this->actingAs($lecteur)->get(route('audit.show', $evtAgent))->assertForbidden();
        $this->actingAs($lecteur)->get(route('audit.show', $evtLecteur))->assertOk();
        $this->actingAs($chef)->get(route('audit.show', $evtAgent))->assertOk();
        $this->actingAs($chef)->get(route('audit.show', $evtLecteur))->assertForbidden();
        $this->actingAs($auditeur)->get(route('audit.show', $evtLecteur))->assertOk();

        $ids = collect($this->actingAs($lecteur)->getJson(route('api.audit.events'))->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($evtLecteur->id));
        $this->assertFalse($ids->contains($evtAgent->id));
        $this->assertSame('propre', $this->actingAs($lecteur)->getJson(route('api.audit.events'))->json('meta.perimetre'));
    }

    public function test_rec_j16_verification_sur_journal_intact_conforme(): void
    {
        $this->besoinSoumis($this->user('chef_service'));
        $integrite = app(AuditIntegriteService::class);
        $s = $integrite->sceller();
        $this->assertNotNull($s);
        Storage::disk('local')->assertExists($s->depot);

        $c = $integrite->verifier();
        $this->assertSame('conforme', $c->resultat, json_encode($c->anomalies));
        $this->assertGreaterThan(0, $c->evenements_verifies);
    }

    public function test_rec_j17_alteration_simulee_detectee_et_alertee(): void
    {
        $this->besoinSoumis($this->user('chef_service'));
        $integrite = app(AuditIntegriteService::class);
        $integrite->sceller();
        $cible = JournalAudit::query()->where('action', 'created')->first();
        // Altération directe en base (contourne l'application ; sur PostgreSQL un déclencheur la refuserait).
        DB::table('journaux_audit')->where('id', $cible->id)->update(['user_nom' => 'Falsificateur']);

        $c = $integrite->verifier();
        $this->assertSame('anomalie', $c->resultat);
        $this->assertContains($cible->id, collect($c->anomalies)->pluck('id')->filter()->all());
        $this->assertTrue(Alerte::query()->where('module', 'audit')->where('code', 'INTEGRITE_JOURNAL')->exists(), 'JNL-014 : alerte levée.');
    }

    public function test_suppression_d_un_evenement_scelle_detectee(): void
    {
        $this->besoinSoumis($this->user('chef_service'));
        $integrite = app(AuditIntegriteService::class);
        $integrite->sceller();
        DB::table('journaux_audit')->where('id', JournalAudit::query()->min('id'))->delete();

        $c = $integrite->verifier();
        $this->assertSame('anomalie', $c->resultat);
        $this->assertContains('comptage', collect($c->anomalies)->pluck('type')->all());
    }

    public function test_rec_j19_decalage_d_horloge_simule_alerte(): void
    {
        config(['audit.horloge.ecart_max_secondes' => -1]);
        $c = app(AuditIntegriteService::class)->verifier();

        $this->assertSame('alerte', $c->horloge['statut']);
        $this->assertTrue(Alerte::query()->where('module', 'audit')->where('code', 'HORLOGE')->exists());
        $this->assertNotNull($this->dernier('horloge_desynchronisee'));
    }

    public function test_rec_j21_echec_de_l_audit_obligatoire_annule_la_decision(): void
    {
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $directeur = $this->user('directeur');
        $tache = $this->tache($besoin);
        JournalAudit::creating(function (JournalAudit $j) {
            if ($j->action === 'workflow_valide') {
                throw new \RuntimeException('Journal indisponible');
            }
        });

        try {
            app(TacheActionService::class)->decider($tache, $directeur, ['decision' => 'valider']);
            $this->fail('La décision ne doit pas être confirmée sans audit.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Journal indisponible', $e->getMessage());
        }

        $this->assertSame('a_traiter', $tache->fresh()->statut);
        $this->assertSame('en_attente', $besoin->fresh()->workflowActif->etapeCourante()->statut);
    }

    public function test_rec_j22_archivage_puis_purge_reglementee_preserve_la_preuve(): void
    {
        $this->besoinSoumis($this->user('chef_service'));
        $integrite = app(AuditIntegriteService::class);
        $integrite->sceller();
        $archive = app(AuditArchivageService::class)->archiver(now()->addDay());
        $this->assertNotNull($archive);
        $this->assertSame('conforme', app(AuditArchivageService::class)->verifier($archive)['resultat']);

        DB::table('journaux_audit')->where('id', '<=', $archive->a_id)->update(['created_at' => now()->subYears(10)]);
        // La remise à une date ancienne simule l'âge des événements ; elle modifie le contenu haché de created_at
        // uniquement pour les lignes sans occurred_at, absentes ici.
        config(['audit.purge.enabled' => true, 'audit.retention.journaux_audit' => 365]);
        $this->artisan('audit:purge-journaux', ['--table' => 'journaux_audit'])->assertSuccessful();

        $this->assertSame(0, JournalAudit::query()->where('id', '<=', $archive->a_id)->count(), 'Plage archivée purgée.');
        $this->assertNotNull($this->dernier('audit_purge'), 'La purge est elle-même journalisée.');
        $c = $integrite->verifier();
        $this->assertSame('conforme', $c->resultat, 'Vérification préservée grâce à l\'archive : '.json_encode($c->anomalies));
    }

    public function test_purge_du_journal_central_refusee_sans_archive(): void
    {
        AuditLogger::evenement('essai');
        DB::table('journaux_audit')->update(['created_at' => now()->subYears(10)]);
        config(['audit.purge.enabled' => true, 'audit.retention.journaux_audit' => 365]);
        $avant = JournalAudit::query()->count();

        $this->artisan('audit:purge-journaux', ['--table' => 'journaux_audit'])->assertSuccessful();

        $this->assertSame($avant, JournalAudit::query()->count());
    }

    public function test_rec_j18_export_autorise_et_trace(): void
    {
        $auditeur = $this->user('auditeur');
        AuditLogger::evenement('essai');

        $r = $this->actingAs($auditeur)->get(route('audit.export', ['format' => 'csv']));
        $r->assertOk();
        $this->assertNotEmpty($r->headers->get('X-Export-Sha256'));
        $export = AuditExport::query()->latest('id')->first();
        $this->assertSame($auditeur->id, (int) $export->user_id);
        $this->assertNotNull($this->dernier('audit_export', $export));

        $this->actingAs($this->user('lecteur'))->get(route('audit.export', ['format' => 'csv']))->assertForbidden();
    }

    public function test_rec_j23_suppression_d_une_notification_sans_effet_sur_le_journal(): void
    {
        $u = $this->user('directeur');
        $this->besoinSoumis($this->user('chef_service'));
        $avant = JournalAudit::query()->count();
        $u->notifications()->delete();
        DB::table('notifications')->delete();

        $this->assertSame($avant, JournalAudit::query()->count());
    }

    public function test_api_lecture_seule_et_pages(): void
    {
        $auditeur = $this->user('auditeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $j = $this->dernier('created', $besoin);

        $this->actingAs($auditeur)->postJson('/api/v1/audit/events', ['action' => 'fabrique'])->assertStatus(405);
        $this->actingAs($auditeur)->getJson(route('api.audit.event', $j))->assertOk()->assertJsonPath('data.integrite', 'conforme');
        $this->actingAs($auditeur)->getJson(route('api.audit.dashboard'))->assertOk();
        $this->actingAs($auditeur)->getJson(route('api.audit.subject', ['ExpressionBesoin', $besoin->id]))->assertOk();
        $this->actingAs($auditeur)->postJson(route('api.audit.integrity-checks.store'))->assertCreated();

        foreach (['audit.tableau-de-bord', 'audit.index', 'audit.securite', 'audit.integrite'] as $route) {
            $this->actingAs($auditeur)->get(route($route))->assertOk();
        }
        $this->actingAs($auditeur)->get(route('audit.show', $j))->assertOk()->assertSee('Empreinte recalculée');
        $this->actingAs($auditeur)->get(route('audit.dossier', ['ExpressionBesoin', $besoin->id]))->assertOk()->assertSee('EB.CREATED');
        $this->actingAs($auditeur)->get(route('audit.utilisateur', $auditeur))->assertOk();
    }

    public function test_alerte_de_journalisation_suit_son_cycle_et_son_historique(): void
    {
        $auditeur = $this->user('auditeur');
        config(['audit.horloge.ecart_max_secondes' => -1]);
        app(AuditIntegriteService::class)->verifier();
        $alerte = Alerte::query()->where('module', 'audit')->firstOrFail();

        $this->actingAs($auditeur)->postJson(route('api.audit.alert-decision', $alerte), ['decision' => 'resoudre', 'commentaire' => 'x'])->assertStatus(422);
        $this->actingAs($auditeur)->postJson(route('api.audit.alert-decision', $alerte), ['decision' => 'qualifier'])->assertOk()->assertJsonPath('data.statut', 'qualifiee');
        $this->actingAs($auditeur)->postJson(route('api.audit.alert-decision', $alerte), ['decision' => 'resoudre'])->assertStatus(422);
        $this->actingAs($auditeur)->postJson(route('api.audit.alert-decision', $alerte), ['decision' => 'resoudre', 'commentaire' => 'NTP rétabli'])->assertOk();

        $this->assertGreaterThanOrEqual(2, JournalAudit::query()->where('auditable_type', Alerte::class)->where('auditable_id', $alerte->id)->where('action', 'updated')->count(), 'JNL-022 : historique des alertes.');
    }
}
