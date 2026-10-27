<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\EngagementService;
use App\Services\Workflow\TacheActionService;
use App\Services\Workflow\TacheQueryService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/**
 * Cohérence Workflow / Notifications / Mes Tâches sur la chaîne de dépense : toute action humaine attendue
 * (étape de circuit ou action hors circuit) est une tâche visible chez son titulaire, notifiée, et close
 * quand elle est réalisée.
 */
class MesTachesSynchronisationTest extends TestCase
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

    private function user(string $role, ?Structure $structure = null): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif', 'structure_id' => $structure?->id])->assignRole($role);
    }

    /** @return list<WorkflowTache> */
    private function taches(Model $objet, ?string $nature = null): array
    {
        return WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())
            ->when($nature, fn ($q) => $q->where('nature', $nature))->orderBy('id')->get()->all();
    }

    private function ouverte(Model $objet, string $nature): ?WorkflowTache
    {
        return WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())
            ->where('nature', $nature)->ouvertes()->first();
    }

    private function mesTaches(User $user): \Illuminate\Support\Collection
    {
        return app(TacheQueryService::class)->collection($user, 'mes');
    }

    private function notificationAction(User $user, Model $objet): ?object
    {
        return DB::table('notifications')->where('notifiable_id', $user->id)
            ->where('ressource_type', $objet->getMorphClass())->where('ressource_id', $objet->getKey())
            ->where('action_statut', 'en_attente')->first();
    }

    private function besoinBrouillon(User $demandeur): ExpressionBesoin
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-SYN-'.uniqid(), 1_000_000);

        return ExpressionBesoin::create([
            'numero' => 'EB-SYN-'.substr(uniqid(), -6), 'exercice_id' => $this->exercice->id, 'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id, 'demandeur_id' => $demandeur->id, 'objet' => 'Besoin synchronisation',
            'montant_estime' => 50_000, 'priorite' => 'moyenne', 'statut' => 'brouillon', 'categorie' => 'pap',
        ]);
    }

    private function ordonnancement(float $montant, User $gestionnaire, string $statut = 'brouillon'): Ordonnancement
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-ORD-'.uniqid(), $montant * 2);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, $montant, 'EB-ORD-'.substr(uniqid(), -6));
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'ORD'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => $montant]],
            $gestionnaire,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-'.substr(uniqid(), -6), 'engagement_id' => $engagement->id, 'montant' => $montant, 'statut' => 'valide', 'compteurs_appliques' => true]);

        return Ordonnancement::create(['numero' => 'ORD-'.substr(uniqid(), -6), 'liquidation_id' => $liq->id, 'montant' => $montant, 'statut' => $statut, 'cree_par' => $gestionnaire->id]);
    }

    // ── EB : préparation déléguée (anomalie 2.1) ─────────────────────────────────────────────

    public function test_eb_preparee_par_le_point_focal_cree_une_tache_et_une_notification_chez_le_chef_de_service(): void
    {
        $chef = $this->user('chef_service');
        $besoin = $this->besoinBrouillon($this->user('point_focal'));

        $tache = $this->ouverte($besoin, 'preparation');
        $this->assertNotNull($tache, 'EB préparée par un agent qui ne peut pas la soumettre : tâche « à soumettre » attendue.');
        $this->assertSame('eb_soumettre', $tache->action_code);
        $this->assertSame('chef_service', $tache->role);
        $this->assertTrue($this->mesTaches($chef)->contains('id', $tache->id), 'La tâche doit figurer dans « Mes Tâches » du chef de service.');
        $this->assertNotNull($this->notificationAction($chef, $besoin), 'Le chef de service doit être notifié.');
        $this->assertContains('traiter_dossier', app(TacheActionService::class)->actionsDisponibles($tache, $chef));
    }

    public function test_eb_brouillon_d_un_agent_qui_peut_soumettre_ne_cree_pas_de_tache_artificielle(): void
    {
        $besoin = $this->besoinBrouillon($this->user('chef_service'));

        $this->assertSame([], $this->taches($besoin), 'Un brouillon non soumis par son auteur habilité n\'est pas une tâche.');
    }

    public function test_soumission_par_le_chef_clot_la_preparation_et_affecte_le_directeur(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $besoin = $this->besoinBrouillon($this->user('point_focal'));
        $prep = $this->ouverte($besoin, 'preparation');

        Sanctum::actingAs($chef, ['*']);
        $this->postJson(route('api.besoins.soumettre', $besoin))->assertOk();

        $prep->refresh();
        $this->assertSame('terminee', $prep->statut);
        $this->assertSame('traitee', $prep->decision);
        $this->assertNull($this->notificationAction($chef, $besoin), 'La notification d\'action du chef est close avec sa tâche.');
        $validation = $this->ouverte($besoin, 'validation');
        $this->assertSame('directeur', $validation->role);
        $this->assertTrue($this->mesTaches($directeur)->contains('id', $validation->id));
        $this->assertNotNull($this->notificationAction($directeur, $besoin), 'Notification et tâche du directeur coexistent (anomalie 2.2).');
    }

    // ── EB : retour pour correction ───────────────────────────────────────────────────────────

    public function test_renvoi_retourne_au_demandeur_puis_la_resoumission_reprend_au_niveau_qui_a_renvoye(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $commissaire = $this->user('commissaire');
        $besoin = $this->besoinBrouillon($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);
        $this->workflow->valider($besoin->fresh()->workflowActif, $directeur);

        $this->workflow->renvoyer($besoin->fresh()->workflowActif, $commissaire, 'Préciser les quantités.');

        $besoin->refresh();
        $this->assertSame('a_corriger', $besoin->statut);
        $this->assertNull($this->ouverte($besoin, 'validation'), 'Aucun valideur ne doit avoir de tâche pendant la correction.');
        $correction = $this->ouverte($besoin, 'preparation');
        $this->assertSame('eb_corriger', $correction?->action_code);
        $this->assertTrue($this->mesTaches($chef)->contains('id', $correction->id), 'Le demandeur retrouve le dossier à corriger.');

        Sanctum::actingAs($chef, ['*']);
        $this->postJson(route('api.besoins.soumettre', $besoin))->assertOk();

        $besoin->refresh();
        $this->assertSame('soumis', $besoin->statut);
        $this->assertSame('terminee', $correction->fresh()->statut);
        $reprise = $this->ouverte($besoin, 'validation');
        $this->assertSame('commissaire', $reprise->role, 'Le circuit reprend chez le commissaire (niveau qui a renvoyé), pas chez le directeur.');
        $this->assertTrue($this->mesTaches($commissaire)->contains('id', $reprise->id));
        $this->assertFalse($this->mesTaches($directeur)->contains('id', $reprise->id));
    }

    // ── ENG / LIQ / ORD / PAY : actions hors circuit ─────────────────────────────────────────

    public function test_ordonnancement_en_brouillon_est_une_tache_du_gestionnaire_close_a_la_soumission(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement(800_000, $gestionnaire);

        $prep = $this->ouverte($ord, 'preparation');
        $this->assertSame('ord_soumettre', $prep?->action_code);
        $this->assertTrue($this->mesTaches($gestionnaire)->contains('id', $prep->id));

        $sg = $this->user('secretaire_general');
        $this->workflow->demarrer($ord, 'ORDONNANCEMENT', $gestionnaire);

        $this->assertSame('terminee', $prep->fresh()->statut);
        $signature = $this->ouverte($ord, 'validation');
        $this->assertSame('secretaire_general', $signature->role, 'ORD ≤ seuil : signature du SG.');
        $this->assertTrue($this->mesTaches($sg)->contains('id', $signature->id));
    }

    public function test_paiement_a_preparer_rattache_la_notification_metier_sans_doublon(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $comptable = $this->user('comptable');
        $ord = $this->ordonnancement(600_000, $gestionnaire);
        $this->viserOrdonnancement($ord);

        $paiement = Paiement::where('ordonnancement_id', $ord->id)->firstOrFail();
        $prep = $this->ouverte($paiement, 'preparation');
        $this->assertSame('pay_preparer', $prep?->action_code);
        $this->assertTrue($this->mesTaches($comptable)->contains('id', $prep->id));

        $actions = DB::table('notifications')->where('notifiable_id', $comptable->id)
            ->where('ressource_type', $paiement->getMorphClass())->where('ressource_id', $paiement->id)->where('action_statut', 'en_attente')->get();
        $this->assertCount(1, $actions, 'Une seule notification d\'action pour le comptable (la notification métier porte la tâche).');
        $this->assertSame($prep->id, data_get(json_decode($actions->first()->data, true), 'meta.tache_id'));
    }

    public function test_paiement_partiel_cree_une_tache_de_solde_puis_la_clot_au_paiement_suivant(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $comptable = $this->user('comptable');
        $ord = $this->ordonnancement(1_000_000, $gestionnaire, 'valide');
        Paiement::create(['numero' => 'PAY-P1', 'ordonnancement_id' => $ord->id, 'montant' => 400_000, 'devise' => 'XAF',
            'statut' => 'valide', 'cree_par' => $gestionnaire->id, 'motif_partiel' => 'Premier versement']);

        $solde = $this->ouverte($ord, 'preparation');
        $this->assertSame('pay_solde', $solde?->action_code, 'Solde de 600 000 restant : tâche du comptable.');
        $this->assertTrue($this->mesTaches($comptable)->contains('id', $solde->id));

        $second = Paiement::create(['numero' => 'PAY-P2', 'ordonnancement_id' => $ord->id, 'montant' => 600_000, 'devise' => 'XAF',
            'statut' => 'brouillon', 'cree_par' => $comptable->id]);

        $this->assertSame('terminee', $solde->fresh()->statut, 'Le solde est pris en charge par le nouveau paiement.');
        $this->assertSame('pay_preparer', $this->ouverte($second, 'preparation')?->action_code);
    }

    public function test_paiement_integral_ne_laisse_aucune_tache_de_solde(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement(500_000, $gestionnaire, 'valide');
        Paiement::create(['numero' => 'PAY-T', 'ordonnancement_id' => $ord->id, 'montant' => 500_000, 'devise' => 'XAF',
            'statut' => 'valide', 'cree_par' => $gestionnaire->id]);

        $this->assertNull($this->ouverte($ord, 'preparation'));
    }

    public function test_liquidation_rejetee_cree_une_tache_de_correction(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ord = $this->ordonnancement(300_000, $gestionnaire);
        $liq = $ord->liquidation;
        $liq->update(['statut' => 'rejete']);

        $this->assertSame('liq_corriger', $this->ouverte($liq, 'preparation')?->action_code);
        $this->assertSame('gestionnaire_budgetaire', $this->ouverte($liq, 'preparation')->role);
    }

    public function test_engagement_en_attente_d_arbitrage_est_une_tache(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $engagement = $this->ordonnancement(200_000, $gestionnaire)->liquidation->engagement;
        $engagement->update(['statut' => 'en_attente_arbitrage']);

        $this->assertSame('eng_arbitrage', $this->ouverte($engagement, 'preparation')?->action_code);
    }

    public function test_une_tache_de_preparation_ne_se_decide_pas_dans_mes_taches(): void
    {
        $chef = $this->user('chef_service');
        $tache = $this->ouverte($this->besoinBrouillon($this->user('point_focal')), 'preparation');

        $this->expectException(RuntimeException::class);
        app(TacheActionService::class)->decider($tache, $chef, ['decision' => 'valider']);
    }

    public function test_la_synchronisation_est_idempotente(): void
    {
        $besoin = $this->besoinBrouillon($this->user('point_focal'));
        $service = app(\App\Services\Workflow\TachePreparationService::class);
        $service->synchroniser(ExpressionBesoin::class, $besoin->id);
        $service->synchroniser(ExpressionBesoin::class, $besoin->id);

        $this->assertCount(1, $this->taches($besoin, 'preparation'));
    }

    // ── Affichage : dernières tâches reçues en tête ───────────────────────────────────────────

    public function test_les_dernieres_taches_recues_apparaissent_en_premier_tous_processus(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $ancienne = $this->ouverte($this->ordonnancement(100_000, $gestionnaire), 'preparation');
        $ancienne->forceFill(['created_at' => now()->subDays(3), 'echeance_le' => now()->subDay()])->save();
        $engagement = $this->ordonnancement(100_000, $gestionnaire)->liquidation->engagement;
        $engagement->update(['statut' => 'rejete']);
        $recente = $this->ouverte($engagement, 'preparation');

        $liste = app(TacheQueryService::class)->lister($gestionnaire, 'mes')->getCollection();

        $this->assertSame($recente->id, $liste->first()->id, 'La tâche la plus récemment reçue est en tête, même si une autre est en retard.');
    }

    // ── Cloisonnement : la règle d'affectation est la même pour notification, tâche et moteur ──

    public function test_le_directeur_de_la_direction_concernee_est_seul_destinataire_quand_il_est_affecte(): void
    {
        $direction = Structure::create(['code' => 'DIR-A', 'libelle' => 'Direction A', 'type' => 'direction', 'actif' => true]);
        $service = Structure::create(['code' => 'SVC-A', 'libelle' => 'Service A', 'type' => 'service', 'parent_id' => $direction->id, 'actif' => true]);
        $autre = Structure::create(['code' => 'DIR-B', 'libelle' => 'Direction B', 'type' => 'direction', 'actif' => true]);
        $directeurA = $this->user('directeur', $direction);
        $directeurB = $this->user('directeur', $autre);
        $chef = $this->user('chef_service', $service);
        $besoin = $this->besoinBrouillon($chef);
        $besoin->update(['structure_id' => $service->id]);

        $this->workflow->demarrer($besoin->fresh(), 'BESOIN_PAP', $chef);
        $tache = $this->ouverte($besoin, 'validation');

        $this->assertFalse((bool) $tache->affectation_defaut);
        $this->assertTrue($this->mesTaches($directeurA)->contains('id', $tache->id));
        $this->assertNotNull($this->notificationAction($directeurA, $besoin));
        $this->assertFalse($this->mesTaches($directeurB)->contains('id', $tache->id), 'Un directeur d\'une autre direction ne voit pas la tâche.');
        $this->assertNull($this->notificationAction($directeurB, $besoin), 'Ni ne reçoit la notification.');
        $this->assertFalse($this->workflow->peutAgir($besoin->fresh()->workflowActif, $directeurB), 'Ni ne peut agir (moteur aligné).');
    }

    public function test_sans_titulaire_organique_l_affectation_par_defaut_est_signalee(): void
    {
        $directeur1 = $this->user('directeur');
        $directeur2 = $this->user('directeur');
        $chef = $this->user('chef_service');
        $besoin = $this->besoinBrouillon($chef);

        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);
        $tache = $this->ouverte($besoin, 'validation');

        $this->assertTrue((bool) $tache->affectation_defaut, 'Aucun directeur rattaché : affectation par défaut signalée.');
        foreach ([$directeur1, $directeur2] as $d) {
            $this->assertTrue($this->mesTaches($d)->contains('id', $tache->id));
            $this->assertNotNull($this->notificationAction($d, $besoin));
        }
    }

    public function test_chaque_notification_d_action_d_etape_correspond_a_une_tache_visible(): void
    {
        $chef = $this->user('chef_service');
        $this->user('directeur');
        $this->user('directeur');
        $besoin = $this->besoinBrouillon($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);

        $notifications = DB::table('notifications')->where('type_code', 'workflow.etape_en_attente')->where('action_statut', 'en_attente')->get();
        $this->assertNotEmpty($notifications);
        foreach ($notifications as $n) {
            $tacheId = data_get(json_decode($n->data, true), 'meta.tache_id');
            $this->assertTrue($this->mesTaches(User::find($n->notifiable_id))->contains('id', $tacheId), "Notification {$n->id} sans tâche visible chez son destinataire.");
        }
    }
}
