<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Structure;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Services\EngagementService;
use App\Services\Workflow\WorkflowCircuitAdminService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Recette du module Workflow (référentiel du 25/10/2026, scénarios WF-001 → WF-022 et anomalies A1 → A6). */
class WorkflowConformiteTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function besoin(User $demandeur): ExpressionBesoin
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-WF-'.uniqid(), 100_000);

        return ExpressionBesoin::create([
            'numero' => 'EB-WF-'.substr(uniqid(), -6), 'exercice_id' => $this->exercice->id, 'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id, 'demandeur_id' => $demandeur->id, 'objet' => 'Besoin workflow',
            'montant_estime' => 500, 'priorite' => 'moyenne', 'statut' => 'brouillon',
        ]);
    }

    private function requeteCircuit(Workflow $workflow, array $etapes): Request
    {
        return Request::create('/', 'PUT', [
            'code' => $workflow->code, 'libelle' => $workflow->libelle, 'type_objet' => $workflow->type_objet, 'actif' => 1,
            'etapes' => $etapes,
        ]);
    }

    private function etapesActuelles(Workflow $workflow): array
    {
        return $workflow->fresh('etapes')->etapes->map(fn ($e) => ['id' => $e->id, 'ordre' => $e->ordre, 'libelle' => $e->libelle, 'role' => $e->role, 'delai_jours' => $e->delai_jours])->values()->all();
    }

    public function test_wf001_wf002_eb_pap_appui_route_vers_le_sg(): void
    {
        $besoin = $this->besoin($this->user('chef_service'));
        $instance = $this->workflow->demarrer($besoin, 'BESOIN_PAP_APPUI', $this->user('chef_service'));
        $this->assertSame('directeur', $instance->etapeCourante()->role);
        $this->workflow->valider($instance, $this->user('directeur'));
        $this->assertSame('secretaire_general', $instance->fresh()->etapeCourante()->role);
    }

    public function test_wf004_double_demarrage_et_double_validation_sans_doublon(): void
    {
        $initiateur = $this->user('chef_service');
        $besoin = $this->besoin($initiateur);
        $instance = $this->workflow->demarrer($besoin, 'BESOIN_PAP', $initiateur);

        $this->expectExceptionObject(new RuntimeException('Un circuit est déjà en cours sur cet objet.'));
        try {
            $this->workflow->demarrer($besoin->fresh(), 'BESOIN_PAP', $initiateur);
        } finally {
            $this->assertSame(1, WorkflowInstance::where('objet_id', $besoin->id)->where('objet_type', $besoin->getMorphClass())->count());
            // Double clic sur « Valider » : la seconde décision visant la même étape est refusée.
            $etapeId = $instance->etapeCourante()->id;
            $directeur = $this->user('directeur');
            $this->workflow->valider($instance->fresh(), $directeur, null, $etapeId);
            try {
                $this->workflow->valider($instance->fresh(), $this->user('directeur'), null, $etapeId);
                $this->fail('La seconde validation de la même étape doit être refusée.');
            } catch (RuntimeException) {
            }
            $this->assertSame(1, WorkflowInstanceEtape::where('instance_id', $instance->id)->where('statut', 'valide')->count());
        }
    }

    public function test_wf004b_unicite_de_l_instance_en_cours_garantie_par_la_base(): void
    {
        $initiateur = $this->user('chef_service');
        $besoin = $this->besoin($initiateur);
        $instance = $this->workflow->demarrer($besoin, 'BESOIN_PAP', $initiateur);

        $this->expectException(\Illuminate\Database\QueryException::class);
        WorkflowInstance::create([
            'workflow_id' => $instance->workflow_id, 'objet_type' => $instance->objet_type, 'objet_id' => $instance->objet_id,
            'statut' => 'en_cours', 'etape_courante' => 1, 'demarre_par' => $initiateur->id,
        ]);
    }

    public function test_wf003_engagement_cree_une_seule_fois_depuis_l_eb(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-WF-ENG', 10_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 700, 'EB-WF-ENG');
        $service = app(\App\Services\Depense\BesoinVersEngagementService::class);

        $service->assurerDepuisBesoinApprouve($besoin);
        $service->assurerDepuisBesoinApprouve($besoin->fresh());
        $this->assertSame(1, Engagement::where('expression_besoin_id', $besoin->id)->count());
    }

    public function test_wf010_decision_par_acteur_non_habilite_refusee_en_403_et_tracee(): void
    {
        $initiateur = $this->user('chef_service');
        $instance = $this->workflow->demarrer($this->besoin($initiateur), 'BESOIN_PAP', $initiateur);
        $intrus = $this->user('comptable');
        $intrus->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('workflow.view', 'web'), \Spatie\Permission\Models\Permission::findOrCreate('workflow.validate', 'web'));

        $this->actingAs($intrus)->postJson(route('api.workflow.decision', $instance), ['decision' => 'valider'])->assertForbidden();
        $this->assertDatabaseHas('journaux_audit', ['action' => 'workflow_decision_refusee', 'user_id' => $intrus->id]);
        $this->assertSame('en_attente', $instance->fresh()->etapeCourante()->statut);
    }

    public function test_wf013_retour_sans_motif_refuse_rejet_sans_categorie_refuse_en_api(): void
    {
        $initiateur = $this->user('chef_service');
        $instance = $this->workflow->demarrer($this->besoin($initiateur), 'BESOIN_PAP', $initiateur);
        $directeur = $this->user('directeur');
        $directeur->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('workflow.view', 'web'), \Spatie\Permission\Models\Permission::findOrCreate('workflow.validate', 'web'));

        $this->actingAs($directeur)->postJson(route('api.workflow.decision', $instance), ['decision' => 'renvoyer'])->assertStatus(422);
        $this->actingAs($directeur)->postJson(route('api.workflow.decision', $instance), ['decision' => 'rejeter', 'observation' => 'Hors budget'])
            ->assertStatus(422)->assertJsonValidationErrors('categorie_rejet');
        $this->assertSame('en_attente', $instance->fresh()->etapeCourante()->statut);
    }

    public function test_wf018_decision_concurrente_obsolete_renvoie_409_et_idempotence(): void
    {
        $initiateur = $this->user('chef_service');
        $instance = $this->workflow->demarrer($this->besoin($initiateur), 'BESOIN_PAP', $initiateur);
        $etapeId = $instance->etapeCourante()->id;
        $directeur = $this->user('directeur');
        $directeur->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('workflow.view', 'web'), \Spatie\Permission\Models\Permission::findOrCreate('workflow.validate', 'web'));

        $cle = (string) \Illuminate\Support\Str::uuid();
        $this->actingAs($directeur)->withHeader('Idempotency-Key', $cle)
            ->postJson(route('api.workflow.decision', $instance), ['decision' => 'valider', 'etape_attendue_id' => $etapeId])->assertOk();
        // Rejeu de la même commande : même réponse, aucune seconde transition.
        $this->actingAs($directeur)->withHeader('Idempotency-Key', $cle)
            ->postJson(route('api.workflow.decision', $instance), ['decision' => 'valider', 'etape_attendue_id' => $etapeId])->assertOk();
        $this->assertSame(1, WorkflowInstanceEtape::where('instance_id', $instance->id)->where('statut', 'valide')->count());

        // Commande visant une étape déjà traitée : conflit.
        $commissaire = $this->user('commissaire');
        $commissaire->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('workflow.view', 'web'), \Spatie\Permission\Models\Permission::findOrCreate('workflow.validate', 'web'));
        $this->actingAs($commissaire)->postJson(route('api.workflow.decision', $instance), ['decision' => 'valider', 'etape_attendue_id' => $etapeId])
            ->assertStatus(409);
    }

    public function test_detail_instance_api_expose_l_historique_des_decisions(): void
    {
        $initiateur = $this->user('chef_service');
        $instance = $this->workflow->demarrer($this->besoin($initiateur), 'BESOIN_PAP', $initiateur);
        $directeur = $this->user('directeur');
        $this->workflow->valider($instance, $directeur, 'Conforme');
        $auditeur = $this->user('auditeur');
        $auditeur->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('workflow.view', 'web'), \Spatie\Permission\Models\Permission::findOrCreate('workflow.audit', 'web'));

        $r = $this->actingAs($auditeur)->getJson(route('api.workflow.instance', $instance))->assertOk();
        $this->assertSame('Conforme', $r->json('data.historique.0.observation'));
        $this->assertSame($directeur->name, $r->json('data.historique.0.acteur'));
        $this->assertNotNull($r->json('data.version'));
        $this->assertNotEmpty($r->json('data.etapes.0.libelle'));
    }

    public function test_wf014_delegation_expiree_ne_permet_plus_de_decider(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $president = $this->user('president');
        $structure = Structure::query()->firstOrNew(['code' => 'PRES']);
        $structure->fill(['libelle' => $structure->libelle ?: 'Présidence', 'sigle' => 'PRES', 'type' => $structure->type ?: 'presidence', 'actif' => true, 'responsable_user_id' => $president->id])->save();
        $delegue = User::factory()->create(['actif' => true, 'statut' => 'actif']);
        $delegation = app(\App\Services\Institution\InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $structure->id, 'delegue_par' => $president->id, 'delegue_a' => $delegue->id,
            'date_debut' => now()->subDays(10)->toDateString(), 'motif' => 'Délégation d\'ordonnancement',
        ]);
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-WF-DEL', 8_000_000);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, 5_000_001, 'EB-WF-DEL');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'DEL'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 5_000_001]], $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-WF-DEL', 'engagement_id' => $engagement->id, 'montant' => 5_000_001, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-WF-DEL', 'liquidation_id' => $liq->id, 'montant' => 5_000_001, 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id]);
        $instance = $this->workflow->demarrer($ord, 'ORDONNANCEMENT_PRESIDENT', $gestionnaire);
        $this->assertTrue($this->workflow->peutAgir($instance, $delegue), 'Délégation en cours : le délégataire peut agir.');

        \App\Models\Delegation::query()->whereKey($delegation->id)->update(['date_fin' => now()->subDay()->toDateString()]);

        $this->assertFalse($this->workflow->peutAgir($instance->fresh(), $delegue), 'Délégation expirée : plus aucun droit.');
        $this->expectException(RuntimeException::class);
        $this->workflow->valider($instance->fresh(), $delegue);
    }

    public function test_a1_etape_utilisee_par_des_dossiers_non_supprimable_historique_conserve(): void
    {
        $initiateur = $this->user('chef_service');
        $instance = $this->workflow->demarrer($this->besoin($initiateur), 'BESOIN_PAP', $initiateur);
        $this->workflow->valider($instance, $this->user('directeur'), 'Vu');
        $workflow = Workflow::where('code', 'BESOIN_PAP')->firstOrFail();
        $etapes = $this->etapesActuelles($workflow);

        try {
            app(WorkflowCircuitAdminService::class)->modifier($this->requeteCircuit($workflow, [$etapes[1]]), $workflow);
            $this->fail('Retirer une étape utilisée doit être refusé.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('etapes', $e->errors());
        }
        $this->assertSame(2, WorkflowInstanceEtape::where('instance_id', $instance->id)->count());
        $this->assertSame('Vu', WorkflowInstanceEtape::where('instance_id', $instance->id)->where('ordre', 1)->value('observation'));

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\WorkflowEtape::query()->whereKey($etapes[0]['id'])->delete();   // la base refuse aussi
    }

    public function test_a3_publication_bloquee_si_circuit_incoherent(): void
    {
        $workflow = Workflow::where('code', 'BESOIN_PAP')->firstOrFail();
        $admin = app(WorkflowCircuitAdminService::class);

        foreach ([
            [['ordre' => 1, 'libelle' => 'A', 'role' => 'directeur'], ['ordre' => 1, 'libelle' => 'B', 'role' => 'commissaire']],
            [['ordre' => 1, 'libelle' => 'A', 'role' => 'role_inexistant']],
            [['ordre' => 1, 'libelle' => 'A', 'role' => 'directeur', 'delai_jours' => -2]],
            [],
        ] as $etapes) {
            try {
                $admin->modifier($this->requeteCircuit($workflow, $etapes), $workflow);
                $this->fail('Circuit incohérent accepté : '.json_encode($etapes));
            } catch (ValidationException) {
            }
        }
        $this->assertSame(2, $workflow->fresh('etapes')->etapes->count(), 'Définition inchangée après refus.');
    }

    public function test_wf020_nouvelle_version_sans_effet_sur_les_instances_en_cours(): void
    {
        $initiateur = $this->user('chef_service');
        $workflow = Workflow::where('code', 'BESOIN_PAP')->firstOrFail();
        $enCours = $this->workflow->demarrer($this->besoin($initiateur), 'BESOIN_PAP', $initiateur);
        $versionInitiale = $enCours->workflow_version_id;
        $this->assertNotNull($versionInitiale, 'Instance épinglée à la version publiée.');

        $etapes = $this->etapesActuelles($workflow);
        $etapes[0]['libelle'] = 'Validation du Directeur (révisée)';
        $etapes[] = ['ordre' => 3, 'libelle' => 'Contrôle budgétaire', 'role' => 'controleur_financier', 'delai_jours' => 3];
        app(WorkflowCircuitAdminService::class)->modifier($this->requeteCircuit($workflow, $etapes), $workflow);

        $enCours = $enCours->fresh('etapes');
        $this->assertSame($versionInitiale, $enCours->workflow_version_id);
        $this->assertSame(2, $enCours->etapes->count(), 'Instance en cours non migrée.');
        $this->assertNotSame('Validation du Directeur (révisée)', $enCours->etapes->firstWhere('ordre', 1)->libelle, 'A5 : libellé historique conservé.');

        $nouvelle = $this->workflow->demarrer($this->besoin($initiateur), 'BESOIN_PAP', $initiateur);
        $this->assertNotSame($versionInitiale, $nouvelle->workflow_version_id);
        $this->assertSame(3, $nouvelle->etapes()->count());
        $this->assertSame(2, $workflow->fresh()->versions()->count());
        $this->assertDatabaseHas('journaux_audit', ['action' => 'workflow_version_publiee']);
    }
}
