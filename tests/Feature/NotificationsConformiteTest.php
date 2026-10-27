<?php

namespace Tests\Feature;

use App\Events\NotificationCreee;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Models\WorkflowTacheEvenement;
use App\Services\EngagementService;
use App\Services\Notifications\DepenseNotificationService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationRappelService;
use App\Services\Workflow\TacheActionService;
use App\Services\Workflow\WorkflowNotificationService;
use App\Services\WorkflowService;
use App\Support\NotificationCriticite;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\NotificationsAlertesSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/**
 * Recette du module Notifications (référentiel du 25/10/2026, plan de tests § 23 : NOT-001 → NOT-020).
 */
class NotificationsConformiteTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.enabled' => false, 'broadcasting.default' => 'log']);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class, NotificationsAlertesSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->workflow = app(WorkflowService::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    private function besoinSoumis(User $demandeur, string $circuit = 'BESOIN_PAP'): ExpressionBesoin
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-NOT-'.uniqid(), 100_000);
        $besoin = ExpressionBesoin::create([
            'numero' => 'EB-NOT-'.substr(uniqid(), -6), 'exercice_id' => $this->exercice->id, 'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id, 'demandeur_id' => $demandeur->id, 'objet' => 'Fournitures notifications',
            'montant_estime' => 500, 'priorite' => 'moyenne', 'statut' => 'brouillon',
        ]);
        $this->workflow->demarrer($besoin, $circuit, $demandeur);

        return $besoin->fresh();
    }

    private function notificationDe(User $user, ?string $type = null): ?DatabaseNotification
    {
        return $user->notifications()->when($type, fn ($q) => $q->where('type_code', $type))->latest()->first();
    }

    /** Ordonnancement validé de montant donné (engagement et liquidation visés). */
    private function ordonnancement(float $montant, User $gestionnaire): Ordonnancement
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

        return Ordonnancement::create(['numero' => 'ORD-'.substr(uniqid(), -6), 'liquidation_id' => $liq->id, 'montant' => $montant, 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id]);
    }

    public function test_not001_eb_soumise_notifie_le_validateur_avec_action_et_dossier(): void
    {
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));

        $n = $this->notificationDe($directeur, 'workflow.etape_en_attente');
        $this->assertNotNull($n, 'Le directeur doit être notifié de la soumission.');
        $this->assertSame('action', $n->nature);
        $this->assertSame('en_attente', $n->action_statut);
        $this->assertSame($besoin->getMorphClass(), $n->ressource_type);
        $this->assertSame($besoin->id, (int) $n->ressource_id);
        $this->assertSame($besoin->numero, $n->reference);
        $this->assertStringStartsWith('/mes-taches/', (string) data_get($n->data, 'url'));
    }

    public function test_not002_validation_cloture_l_action_et_notifie_l_acteur_suivant(): void
    {
        $directeur = $this->user('directeur');
        $commissaire = $this->user('commissaire');
        $besoin = $this->besoinSoumis($this->user('chef_service'));

        $this->workflow->valider($besoin->fresh()->workflowActif, $directeur);

        $this->assertSame('traitee', $this->notificationDe($directeur, 'workflow.etape_en_attente')->action_statut, 'NOT-011 : action obsolète close.');
        $suivante = $this->notificationDe($commissaire, 'workflow.etape_en_attente');
        $this->assertNotNull($suivante);
        $this->assertSame('en_attente', $suivante->action_statut);
    }

    public function test_not003_rejet_et_retour_notifient_l_initiateur(): void
    {
        $initiateur = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($initiateur);
        $this->workflow->rejeter($besoin->fresh()->workflowActif, $directeur, 'Pièces insuffisantes', 'documentaire');

        $n = $this->notificationDe($initiateur, 'workflow.decision');
        $this->assertNotNull($n, 'L\'initiateur doit être informé du rejet.');
        $this->assertStringContainsString('rejeté', data_get($n->data, 'message'));
        $this->assertStringContainsString('Pièces insuffisantes', data_get($n->data, 'message'));

        $autre = $this->besoinSoumis($initiateur);
        $this->workflow->renvoyer($autre->fresh()->workflowActif, $directeur, 'Préciser les quantités');
        $this->assertTrue($initiateur->notifications()->where('type_code', 'workflow.decision')->get()
            ->contains(fn ($n) => str_contains(data_get($n->data, 'message'), 'retourné pour correction')
                && str_contains(data_get($n->data, 'message'), 'Préciser les quantités')));
    }

    public function test_not003b_validation_finale_notifiee_a_l_initiateur(): void
    {
        $initiateur = $this->user('chef_service');
        $besoin = $this->besoinSoumis($initiateur);
        $this->workflow->valider($besoin->fresh()->workflowActif, $this->user('directeur'));
        $this->workflow->valider($besoin->fresh()->workflowActif, $this->user('commissaire'));

        $this->assertTrue($initiateur->notifications()->where('type_code', 'workflow.decision')
            ->get()->contains(fn ($n) => str_contains(data_get($n->data, 'message'), 'validation finale')));
    }

    public function test_not006_not007_ordonnancement_route_selon_le_seuil(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $sg = $this->user('secretaire_general');
        $president = $this->user('president');
        $seuils = app(\App\Services\Depense\SeuilOrdonnancementService::class);

        $petit = $this->ordonnancement(5_000_000, $gestionnaire);
        $this->workflow->demarrer($petit, $seuils->circuit($petit), $gestionnaire);
        $this->assertNotNull($this->notificationDe($sg, 'workflow.etape_en_attente'), 'ORD ≤ seuil → SG.');
        $this->assertNull($this->notificationDe($president, 'workflow.etape_en_attente'));

        $grand = $this->ordonnancement(5_000_001, $gestionnaire);
        $this->workflow->demarrer($grand, $seuils->circuit($grand), $gestionnaire);
        $this->assertNotNull($this->notificationDe($president, 'workflow.etape_en_attente'), 'ORD > seuil → Président.');
    }

    public function test_not013_signature_ord_notifie_l_agence_comptable_puis_action_close_au_demarrage_du_paiement(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $agentComptable = $this->user('agent_comptable');
        $ord = $this->ordonnancement(800_000, $gestionnaire);
        $this->viserOrdonnancement($ord);

        $n = $this->notificationDe($agentComptable, 'depense.paiement_a_preparer');
        $this->assertNotNull($n, 'L\'Agence comptable doit être notifiée après signature.');
        $this->assertSame('en_attente', $n->action_statut);
        $paiement = Paiement::where('ordonnancement_id', $ord->id)->firstOrFail();

        $this->workflow->demarrer($paiement, 'PAIEMENT', $gestionnaire);
        $this->assertSame('traitee', $n->fresh()->action_statut);
    }

    public function test_not008_paiement_partiel_indique_le_montant_et_le_solde(): void
    {
        $gestionnaire = $this->user('gestionnaire_budgetaire');
        $agentComptable = $this->user('agent_comptable');
        $ord = $this->ordonnancement(1_000_000, $gestionnaire);
        $ord->update(['statut' => 'valide']);
        $paiement = Paiement::create([
            'numero' => 'PAY-PART-1', 'ordonnancement_id' => $ord->id, 'montant' => 400_000, 'devise' => 'XAF',
            'statut' => 'valide', 'cree_par' => $gestionnaire->id, 'motif_partiel' => 'Premier versement',
        ]);
        app(DepenseNotificationService::class)->notifierPaiementValide($paiement);

        $n = $this->notificationDe($agentComptable, 'depense.paiement_valide');
        $this->assertNotNull($n, 'Solde restant : l\'Agence comptable est informée.');
        $this->assertSame('Paiement partiel validé', data_get($n->data, 'titre'));
        $this->assertStringContainsString('solde restant dû 600 000 XAF', data_get($n->data, 'message'));
        $this->assertStringNotContainsString('intégralement', data_get($n->data, 'message'), 'RN-17');
    }

    public function test_not009_not010_clic_ouvre_le_dossier_exact_marque_lu_et_trace(): void
    {
        $directeur = $this->user('directeur');
        $this->besoinSoumis($this->user('chef_service'));
        $n = $this->notificationDe($directeur, 'workflow.etape_en_attente');

        $apercu = $this->actingAs($directeur)->getJson(route('notifications.apercu'))->assertOk();
        $this->assertSame(1, $apercu->json('non_lues'));
        $this->assertTrue($apercu->json('items.0.action.disponible'));

        $this->actingAs($directeur)->get(route('notifications.ouvrir', $n->id))
            ->assertRedirect(data_get($n->data, 'url'))->assertSessionMissing('info');
        $this->assertNotNull($n->fresh()->read_at);
        $this->assertSame('en_attente', $n->fresh()->action_statut, 'RN-05 : lire n\'est pas traiter.');
        $this->assertSame(0, $this->actingAs($directeur)->getJson(route('notifications.apercu'))->json('non_lues'));
        $this->assertDatabaseHas('audit_notifications', ['action' => 'consultation', 'utilisateur_id' => $directeur->id]);
    }

    public function test_not011_action_obsolete_signalee_et_dossier_ouvert_en_consultation(): void
    {
        $directeur = $this->user('directeur');
        $autreDirecteur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $nAutre = $this->notificationDe($autreDirecteur, 'workflow.etape_en_attente');
        $this->assertNotNull($nAutre);

        $this->workflow->valider($besoin->fresh()->workflowActif, $directeur);

        $this->actingAs($autreDirecteur)->get(route('notifications.ouvrir', $nAutre->id))
            ->assertRedirect(route('besoins.show', $besoin, false))
            ->assertSessionHas('info', fn ($m) => str_contains($m, 'déjà été traité'));
        $this->actingAs($autreDirecteur)->getJson(route('api.notifications.destination', $nAutre->id))
            ->assertOk()->assertJsonPath('action_disponible', false);
    }

    public function test_not012_not016_notification_d_un_autre_utilisateur_inaccessible_et_tout_lu_cloisonne(): void
    {
        $directeur = $this->user('directeur');
        $intrus = $this->user('directeur');
        app(NotificationDispatcher::class)->envoyer($directeur, 'systeme.information', 'Note', 'Pour le directeur seulement', '/');
        app(NotificationDispatcher::class)->envoyer($intrus, 'systeme.information', 'Note', 'Pour l\'intrus', '/');
        $n = $this->notificationDe($directeur);

        $this->actingAs($intrus)->get(route('notifications.ouvrir', $n->id))->assertNotFound();
        $this->actingAs($intrus)->post(route('notifications.lue', $n->id))->assertNotFound();
        $this->actingAs($intrus)->getJson(route('api.notifications.show', $n->id))->assertNotFound();

        $this->actingAs($intrus)->post(route('notifications.tout-lu'));
        $this->assertNull($n->fresh()->read_at, 'Tout marquer lu ne touche que les notifications du destinataire.');
    }

    public function test_not015_evenement_repete_sans_doublon(): void
    {
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        app(WorkflowNotificationService::class)->notifierEtapeCourante($besoin->fresh()->workflowActif);
        app(WorkflowNotificationService::class)->notifierEtapeCourante($besoin->fresh()->workflowActif);

        $this->assertSame(1, $directeur->notifications()->where('type_code', 'workflow.etape_en_attente')->count());
    }

    public function test_not017_rappels_paramettres_puis_arret_apres_traitement(): void
    {
        config(['notifications.rappels.jours_ouvrables' => false, 'notifications.rappels.escalade_jours_retard' => 0]);
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $tache = WorkflowTache::query()->ouvertes()->firstOrFail();
        $rappels = app(NotificationRappelService::class);

        $this->assertSame(0, $rappels->traiter(now()->addHours(10))['rappels'], 'Avant 24 h : pas de rappel.');
        $this->assertSame(1, $rappels->traiter(now()->addHours(25))['rappels']);
        $this->assertSame(0, $rappels->traiter(now()->addHours(26))['rappels'], 'Pas de doublon du même niveau.');
        $this->assertSame(1, $rappels->traiter(now()->addHours(49))['rappels'], '2e rappel après 48 h.');
        $this->assertSame(2, WorkflowTacheEvenement::where('workflow_tache_id', $tache->id)->where('type', 'rappel')->count());
        $this->assertSame(2, $directeur->notifications()->where('nature', 'rappel')->count());

        $this->workflow->valider($besoin->fresh()->workflowActif, $directeur);
        $this->assertSame(0, $directeur->notifications()->where('nature', 'rappel')->where('action_statut', 'en_attente')->count(), 'Rappels clos avec la décision.');
        config(['notifications.rappels.second_heures' => 72]);
        $rappels->traiter(now()->addHours(80));
        // RN-09 : plus aucun rappel sur la tâche traitée (l'étape suivante a sa propre tâche).
        $this->assertSame(2, WorkflowTacheEvenement::where('workflow_tache_id', $tache->id)->where('type', 'rappel')->count());
        $this->assertSame(2, $directeur->notifications()->where('nature', 'rappel')->count());
    }

    public function test_not017b_rappels_comptes_en_jours_ouvrables(): void
    {
        config(['notifications.rappels.jours_ouvrables' => true]);
        $service = app(NotificationRappelService::class);
        $vendredi = \Carbon\CarbonImmutable::parse('2026-10-23 10:00', 'Africa/Libreville');
        $lundi = \Carbon\CarbonImmutable::parse('2026-10-26 10:00', 'Africa/Libreville');

        $this->assertEqualsWithDelta(24.0, $service->heuresOuvrables($vendredi, $lundi), 0.01, 'Samedi et dimanche exclus.');
    }

    public function test_not017c_escalade_tracee_une_seule_fois(): void
    {
        config(['notifications.rappels.jours_ouvrables' => false, 'notifications.rappels.escalade_jours_retard' => 1]);
        $this->user('directeur');
        $this->besoinSoumis($this->user('chef_service'));
        $tache = WorkflowTache::query()->ouvertes()->firstOrFail();
        $tache->update(['echeance_le' => now()->subDays(3)]);
        $service = app(NotificationRappelService::class);

        $service->traiter();
        $service->traiter();
        $this->assertSame(1, WorkflowTacheEvenement::where('workflow_tache_id', $tache->id)->where('type', 'escalade')->count());
    }

    public function test_not018_reaffectation_notifie_le_nouveau_responsable(): void
    {
        $admin = $this->user('administrateur');
        $cible = $this->user('directeur');
        $this->user('directeur');
        $this->besoinSoumis($this->user('chef_service'));
        $tache = WorkflowTache::query()->ouvertes()->firstOrFail();

        app(TacheActionService::class)->reaffecter($tache, $admin, $cible, 'Absence du titulaire', $tache->version);

        $n = $this->notificationDe($cible, 'workflow.reaffectation');
        $this->assertNotNull($n);
        $this->assertSame('en_attente', $n->action_statut);
        $this->assertStringContainsString('Absence du titulaire', data_get($n->data, 'message'));
    }

    public function test_not019_dossier_annule_rend_l_action_indisponible(): void
    {
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        $instance = $besoin->fresh()->workflowActif;
        $instance->update(['statut' => 'annule']);
        app(\App\Services\Workflow\TacheWorkflowService::class)->synchroniser($instance);

        $n = $this->notificationDe($directeur, 'workflow.etape_en_attente');
        $this->assertSame('annulee', $n->action_statut);
        $this->actingAs($directeur)->getJson(route('api.notifications.destination', $n->id))->assertJsonPath('action_disponible', false);
    }

    public function test_page_notifications_filtres_et_recherche_cote_serveur(): void
    {
        $directeur = $this->user('directeur');
        $besoin = $this->besoinSoumis($this->user('chef_service'));
        app(NotificationDispatcher::class)->envoyer($directeur, 'systeme.information', 'Maintenance planifiée', 'Coupure samedi', '/');

        $this->actingAs($directeur)->get(route('notifications.index', ['action' => 'en_attente']))
            ->assertOk()->assertSee($besoin->numero)->assertDontSee('Maintenance planifiée');
        $this->actingAs($directeur)->get(route('notifications.index', ['nature' => 'systeme']))
            ->assertOk()->assertSee('Maintenance planifiée')->assertDontSee($besoin->numero);
        $this->actingAs($directeur)->get(route('notifications.index', ['q' => 'maintenance']))
            ->assertOk()->assertSee('Maintenance planifiée');
        $this->actingAs($directeur)->get(route('notifications.index', ['priorite' => 'P3', 'du' => now()->toDateString(), 'au' => now()->toDateString()]))
            ->assertOk()->assertSee($besoin->numero);
        $this->actingAs($directeur)->getJson(route('api.notifications.index', ['nature' => 'action']))
            ->assertOk()->tap(fn ($r) => $this->assertCount(1, $r->json('data.data') ?? $r->json('data')));
    }

    public function test_marquer_non_lue_independant_de_l_action(): void
    {
        $directeur = $this->user('directeur');
        $this->besoinSoumis($this->user('chef_service'));
        $n = $this->notificationDe($directeur);
        $n->markAsRead();

        $this->actingAs($directeur)->post(route('notifications.non-lue', $n->id))->assertRedirect();
        $this->assertNull($n->fresh()->read_at);
        $this->actingAs($directeur)->postJson(route('api.notifications.unread.mark', $n->id))->assertOk();
        $this->actingAs($directeur)->getJson(route('api.notifications.summary'))->assertOk()
            ->assertJsonPath('stats.actions_en_attente', 1);
    }

    public function test_notifications_obligatoires_non_desactivables(): void
    {
        $directeur = $this->user('directeur');
        $directeur->update(['preferences_notifications' => ['actif' => false]]);
        $this->besoinSoumis($this->user('chef_service'));
        app(NotificationDispatcher::class)->envoyer($directeur->fresh(), 'systeme.information', 'Info', 'Information facultative', '/');
        app(NotificationDispatcher::class)->envoyer($directeur->fresh(), 'alerte.institutionnelle', 'Alerte', 'Incident critique', '/', criticite: NotificationCriticite::CRITIQUE);

        $this->assertNotNull($this->notificationDe($directeur, 'workflow.etape_en_attente'), 'Action requise toujours reçue.');
        $this->assertNull($this->notificationDe($directeur, 'systeme.information'), 'Information facultative désactivable.');
    }

    public function test_diffusion_temps_reel_sur_canal_prive_si_diffuseur_configure(): void
    {
        Event::fake([NotificationCreee::class]);
        config(['broadcasting.default' => 'reverb']);
        $directeur = $this->user('directeur');
        app(NotificationDispatcher::class)->envoyer($directeur, 'systeme.information', 'Direct', 'Message en direct', '/');

        Event::assertDispatched(NotificationCreee::class, fn (NotificationCreee $e) => $e->userId === $directeur->id
            && $e->broadcastOn()->name === 'private-App.Models.User.'.$directeur->id && $e->apercu['non_lues'] === 1);
    }

    public function test_pas_de_diffusion_avec_le_pilote_log(): void
    {
        Event::fake([NotificationCreee::class]);
        app(NotificationDispatcher::class)->envoyer($this->user('directeur'), 'systeme.information', 'Note', 'Sans diffusion', '/');

        Event::assertNotDispatched(NotificationCreee::class);
    }

    public function test_cloche_et_panneau_presents_dans_le_gabarit(): void
    {
        $directeur = $this->user('directeur');
        $this->actingAs($directeur)->get(route('notifications.index'))->assertOk()
            ->assertSee('id="cloche-notifications"', false)
            ->assertSee('Tout marquer comme lu')
            ->assertSee('Voir toutes les notifications');
    }

    public function test_not020_migration_retroalimentation_idempotente(): void
    {
        $directeur = $this->user('directeur');
        $this->besoinSoumis($this->user('chef_service'));
        $avant = $directeur->notifications()->get(['id', 'nature', 'action_statut', 'ressource_id'])->toArray();

        $this->artisan('notifications:retroalimenter')->assertSuccessful();
        $this->artisan('notifications:retroalimenter')->assertSuccessful();

        $this->assertSame($avant, $directeur->notifications()->get(['id', 'nature', 'action_statut', 'ressource_id'])->toArray());
    }
}
