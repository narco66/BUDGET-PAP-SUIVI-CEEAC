<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\Depense\TracabiliteDepenseService;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/**
 * Traçabilité intégrale EB → ENG → LIQ → ORD → PAY : origine, position, dernier acteur, acteur attendu,
 * prochain acteur, historique, finances, blocages, recherche universelle et cloisonnement (lecture seule).
 */
class TracabiliteDepenseTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    private WorkflowService $workflow;

    private TracabiliteDepenseService $tracabilite;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false, 'notifications.email.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class, WorkflowSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        $this->workflow = app(WorkflowService::class);
        $this->tracabilite = app(TracabiliteDepenseService::class);
    }

    private function user(string $role, array $attributs = []): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'] + $attributs)->assignRole($role);
    }

    private function besoin(User $demandeur, string $statut = 'brouillon', float $montant = 50_000): ExpressionBesoin
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-TR-'.uniqid(), 10_000_000);

        return ExpressionBesoin::create([
            'numero' => 'EB-TR-'.substr(uniqid(), -6), 'exercice_id' => $this->exercice->id, 'activite_id' => $activite->id,
            'ligne_budgetaire_id' => $ligne->id, 'demandeur_id' => $demandeur->id, 'objet' => 'Besoin traçabilité',
            'montant_estime' => $montant, 'priorite' => 'moyenne', 'statut' => $statut, 'categorie' => 'pap',
        ]);
    }

    /** @return array{0: ExpressionBesoin, 1: Engagement, 2: Liquidation, 3: Ordonnancement} chaîne jusqu'à l'ordonnancement brouillon */
    private function chaine(float $montant, ?User $gestionnaire = null): array
    {
        $gestionnaire ??= $this->user('gestionnaire_budgetaire');
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-CH-'.uniqid(), $montant * 3);
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $gestionnaire, $montant, 'EB-CH-'.substr(uniqid(), -6));
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Fournitures', 'beneficiaire' => 'SOCIETE ALPHA SARL'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => $montant]], $gestionnaire);
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true]);
        $liq = Liquidation::create(['numero' => 'LIQ-'.substr(uniqid(), -6), 'engagement_id' => $engagement->id, 'montant' => $montant, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-'.substr(uniqid(), -6), 'liquidation_id' => $liq->id, 'montant' => $montant, 'statut' => 'brouillon', 'cree_par' => $gestionnaire->id]);

        return [$besoin, $engagement, $liq, $ord];
    }

    // 1. Recherche universelle par référence
    public function test_recherche_par_reference_ouvre_directement_la_fiche_de_tracabilite(): void
    {
        [$besoin, , , $ord] = $this->chaine(400_000);
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->get(route('depense.tracabilite.index', ['q' => $ord->numero]))
            ->assertRedirect(route('depense.tracabilite.show', ['ordonnancement', $ord->id]));
        $this->actingAs($admin)->get(route('depense.tracabilite.index', ['q' => 'alpha sarl', 'aller' => 0]))
            ->assertOk()->assertSee('Bénéficiaire ou objet');
    }

    // 2. Bidirectionnalité : depuis n'importe quel maillon, la même chaîne complète
    public function test_depuis_chaque_maillon_la_chaine_complete_est_reconstituee(): void
    {
        [$besoin, $eng, $liq, $ord] = $this->chaine(400_000);

        foreach ([$besoin, $eng, $liq, $ord] as $maillon) {
            $d = $this->tracabilite->dossier($maillon);
            $this->assertTrue($d['besoin']->is($besoin), 'Origine retrouvée depuis '.$maillon->numero);
            $this->assertSame(['EB', 'ENG', 'LIQ', 'ORD'], $d['noeuds']->pluck('processus')->all());
            $this->assertTrue($d['focal']['objet']->is($maillon));
        }
    }

    // 3. Position, dernier acteur (fonction, structure) et acteur attendu nominatif
    public function test_situation_eb_en_validation_dernier_acteur_et_acteur_attendu(): void
    {
        $chef = $this->user('chef_service', ['name' => 'Chef Service Test']);
        $directeur = $this->user('directeur', ['name' => 'Directeur Test']);
        $besoin = $this->besoin($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);

        $s = $this->tracabilite->dossier($besoin->fresh())['situation'];
        $this->assertSame('en_attente', $s['etat']);
        $this->assertSame(1, $s['etape']['ordre']);
        $this->assertSame('Chef Service Test', $s['dernier']['nom']);
        $this->assertFalse($s['dernier']['automatique']);
        $this->assertStringContainsString('Soumission', $s['dernier']['action']);
        $this->assertSame('directeur', $s['attendu']['role_code']);
        $this->assertContains('Directeur Test', array_column($s['attendu']['titulaires'], 'nom'));
        $this->assertNotEmpty($s['restantes'], 'Les étapes restantes du circuit sont listées.');
        $this->assertNotEmpty($s['prochain']);
    }

    // 4. Retour pour correction : le dossier revient au demandeur, le motif est tracé
    public function test_retour_pour_correction_identifie_le_demandeur_et_le_motif(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur', ['name' => 'Directeur Renvoi']);
        $besoin = $this->besoin($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);
        $this->workflow->renvoyer($besoin->fresh()->workflowActif, $directeur, 'Préciser les quantités.');

        $s = $this->tracabilite->dossier($besoin->fresh())['situation'];
        $this->assertSame('retourne', $s['etat']);
        $this->assertSame('Directeur Renvoi', $s['dernier']['nom']);
        $this->assertSame('Préciser les quantités.', $s['dernier']['commentaire']);
        $this->assertSame('chef_service', $s['attendu']['role_code']);
        $this->assertStringContainsString('orrig', $s['attendu']['action']);
    }

    // 5. Affectation manquante : aucun nom inventé
    public function test_sans_titulaire_actif_affectation_manquante_signalee(): void
    {
        $chef = $this->user('chef_service');
        $besoin = $this->besoin($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);   // aucun directeur actif

        $d = $this->tracabilite->dossier($besoin->fresh());
        $this->assertTrue($d['situation']['attendu']['manquante']);
        $this->assertSame([], $d['situation']['attendu']['titulaires']);
        $this->assertSame('bloque', $d['situation']['etat']);
        $this->assertContains('affectation_manquante', $d['anomalies']->pluck('code')->all());
    }

    // 6. Plusieurs engagements issus d'une même EB : tous tracés
    public function test_liquidations_ordonnancements_et_paiements_multiples_sont_tous_traces(): void
    {
        // Un seul engagement par EB (index unique) ; la multiplicité porte sur les liquidations partielles,
        // les ordonnancements fractionnés et les paiements partiels.
        [$besoin, $eng, $liq1, $ord1] = $this->chaine(900_000);
        $liq2 = Liquidation::create(['numero' => 'LIQ-TR-2', 'engagement_id' => $eng->id, 'montant' => 100_000, 'statut' => 'brouillon']);
        $ord2 = Ordonnancement::create(['numero' => 'ORD-TR-2', 'liquidation_id' => $liq1->id, 'montant' => 50_000, 'statut' => 'brouillon']);
        $p1 = Paiement::create(['numero' => 'PAY-TR-A', 'ordonnancement_id' => $ord1->id, 'montant' => 100_000, 'devise' => 'XAF', 'statut' => 'brouillon']);
        $p2 = Paiement::create(['numero' => 'PAY-TR-B', 'ordonnancement_id' => $ord1->id, 'montant' => 200_000, 'devise' => 'XAF', 'statut' => 'brouillon']);

        $d = $this->tracabilite->dossier($p2);
        $this->assertEqualsCanonicalizing([$liq1->id, $liq2->id], $d['noeuds']->where('processus', 'LIQ')->map(fn ($n) => $n['objet']->id)->values()->all());
        $this->assertEqualsCanonicalizing([$ord1->id, $ord2->id], $d['noeuds']->where('processus', 'ORD')->map(fn ($n) => $n['objet']->id)->values()->all());
        $this->assertEqualsCanonicalizing([$p1->id, $p2->id], $d['noeuds']->where('processus', 'PAY')->map(fn ($n) => $n['objet']->id)->values()->all());
        $this->assertTrue($d['besoin']->is($besoin));
    }

    // 7. Branche conditionnelle d'ordonnancement selon le seuil paramétré
    public function test_prochain_ordonnateur_selon_le_seuil(): void
    {
        [, , $petiteLiq] = $this->chaine(1_000_000);
        [, , $grandeLiq] = $this->chaine(9_000_000);
        $petiteLiq->ordonnancements()->delete();
        $grandeLiq->ordonnancements()->delete();

        $roles = fn (Liquidation $l) => array_column($this->tracabilite->situation($l->fresh())['prochain'], 'role');
        $this->assertContains($this->tracabilite->libelleRole('secretaire_general'), $roles($petiteLiq));
        $this->assertContains($this->tracabilite->libelleRole('president'), $roles($grandeLiq));
    }

    // 8. Paiement partiel : solde restant et prochaine action
    public function test_paiement_partiel_signale_le_solde_et_l_action_attendue(): void
    {
        [, , , $ord] = $this->chaine(1_000_000);
        $this->user('comptable');
        $ord->update(['statut' => 'valide']);
        $p = Paiement::create(['numero' => 'PAY-TR1', 'ordonnancement_id' => $ord->id, 'montant' => 400_000, 'devise' => 'XAF', 'statut' => 'valide', 'motif_partiel' => 'Acompte']);

        $d = $this->tracabilite->dossier($p);
        $this->assertSame(1_000_000.0, $d['finances']['ordonnance']);
        $this->assertSame(400_000.0, $d['finances']['paye']);
        $this->assertSame(600_000.0, $d['finances']['reste_a_payer']);
        $this->assertFalse($d['termine']);
        $pay = $d['noeuds']->firstWhere('processus', 'PAY');
        $this->assertStringContainsString('solde', mb_strtolower($pay['situation']['prochain'][0]['etape']));
    }

    // 9. Historique chronologique fusionné (création + décisions)
    public function test_historique_chronologique_avec_decisions(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $besoin = $this->besoin($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);
        $this->workflow->valider($besoin->fresh()->workflowActif, $directeur, 'Avis favorable');

        $h = collect($this->tracabilite->dossier($besoin->fresh())['historique']);
        $this->assertSame('Création du dossier', $h->first()['action']);
        $this->assertTrue($h->contains(fn ($l) => $l['acteur'] === $directeur->name));
        $dates = $h->pluck('date')->map->getTimestamp()->all();
        $tries = $dates;
        sort($tries);
        $this->assertSame($tries, $dates, 'Historique trié chronologiquement.');
    }

    // 10. Cloisonnement : sans droit de consultation, 403
    public function test_acces_refuse_sans_droit_de_consultation(): void
    {
        [$besoin] = $this->chaine(200_000);
        $intrus = User::factory()->create(['actif' => true, 'statut' => 'actif']);

        $this->actingAs($intrus)->get(route('depense.tracabilite.show', ['besoin', $besoin->id]))->assertForbidden();
        $this->actingAs($intrus)->get(route('depense.tracabilite.index', ['q' => $besoin->numero, 'aller' => 0]))->assertOk()->assertSee('Aucun dossier');
    }

    // 11. Affectataire inactif
    public function test_tache_prise_en_charge_par_un_utilisateur_inactif_est_signalee(): void
    {
        $chef = $this->user('chef_service');
        $directeur = $this->user('directeur');
        $this->user('directeur');
        $besoin = $this->besoin($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);
        WorkflowTache::query()->where('objet_id', $besoin->id)->where('objet_type', $besoin->getMorphClass())->ouvertes()
            ->update(['assigne_a' => $directeur->id, 'statut' => 'en_cours']);
        $directeur->update(['actif' => false]);

        $codes = $this->tracabilite->dossier($besoin->fresh())['anomalies']->pluck('code')->all();
        $this->assertContains('affectataire_inactif', $codes);
    }

    // 12. Lecture seule : aucune écriture
    public function test_la_tracabilite_n_ecrit_rien(): void
    {
        [$besoin, , , $ord] = $this->chaine(7_000_000);
        $tables = ['workflow_taches', 'workflow_instances', 'notifications', 'audit_workflow', 'ordonnancements'];
        $avant = collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
        $ordAvant = $ord->fresh()->getAttributes();

        $this->tracabilite->dossier($ord);
        $this->tracabilite->dossier($besoin);

        $this->assertSame($avant, collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all());
        $this->assertSame($ordAvant, $ord->fresh()->getAttributes(), 'Ordonnateur non écrit (snapshot en lecture seule).');
    }

    // 13. Écran complet
    public function test_ecran_de_tracabilite_s_affiche_avec_ses_onglets(): void
    {
        [, , , $ord] = $this->chaine(500_000);
        $this->user('gestionnaire_budgetaire');

        $this->actingAs($this->user('administrateur'))->get(route('depense.tracabilite.show', ['ordonnancement', $ord->id]))
            ->assertOk()
            ->assertSee('Situation actuelle')->assertSee('Qui doit agir maintenant')
            ->assertSee('Circuit et délais')->assertSee('Historique')->assertSee('Dossiers liés')
            ->assertSee('Finances')->assertSee('Documents')->assertSee('Blocages');
    }

    // 14. Retard
    public function test_etape_en_retard_detectee(): void
    {
        $chef = $this->user('chef_service');
        $this->user('directeur');
        $besoin = $this->besoin($chef);
        $this->workflow->demarrer($besoin, 'BESOIN_PAP', $chef);
        WorkflowTache::query()->where('objet_id', $besoin->id)->where('objet_type', $besoin->getMorphClass())->ouvertes()
            ->update(['echeance_le' => now()->subDays(3)]);

        $d = $this->tracabilite->dossier($besoin->fresh());
        $this->assertTrue($d['situation']['attendu']['en_retard']);
        $this->assertContains('retard', $d['anomalies']->pluck('code')->all());
    }
}
