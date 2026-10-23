<?php

namespace Tests\Feature;

use App\Models\EcritureComptable;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Depense\DepenseAuditService;
use App\Services\EngagementService;
use App\Services\WorkflowService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Phase D5 — audit automatisé complet, parcours E2E HTTP, documentation. */
class DepenseD5Test extends TestCase
{
    use RefreshDatabase, CreatesDepenseChain;

    private Exercice $exercice;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(DepensePermissionsSeeder::class);
        $this->seed(WorkflowSeeder::class);
        $this->workflow = app(WorkflowService::class);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true])->assignRole($role);
    }

    /** @return array<string,\App\Models\User> */
    private function acteurs(): array
    {
        return [
            'chef' => $this->user('chef_service'),
            'directeur' => $this->user('directeur'),
            'cf' => $this->user('controleur_financier'),
            'gestionnaire' => $this->user('gestionnaire_budgetaire'),
            'sg' => $this->user('secretaire_general'),
            'comptable' => $this->user('comptable'),
        ];
    }

    public function test_audit_complet_inclut_sections_et_score(): void
    {
        $rapport = app(DepenseAuditService::class)->executer($this->exercice->id);

        $this->assertArrayHasKey('score_conformite', $rapport);
        $this->assertArrayHasKey('sections', $rapport);
        $this->assertArrayHasKey('amont', $rapport['sections']);
        $this->assertArrayHasKey('compteurs', $rapport['sections']);
        $this->assertArrayHasKey('comptabilite', $rapport['sections']);
        $this->assertSame(100, $rapport['score_conformite']);
    }

    public function test_audit_detecte_paiement_sans_ecriture(): void
    {
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D5-ECR', 5000);
        $user = $this->user('gestionnaire_budgetaire');
        $besoin = $this->besoinValide($this->exercice, $activite, $ligne, $user, 100, 'EB-D5-ECR');
        $engagement = app(EngagementService::class)->creer(
            ['exercice_id' => $this->exercice->id, 'expression_besoin_id' => $besoin->id, 'type' => 'simple', 'objet' => 'Test'],
            [['ligne_budgetaire_id' => $ligne->id, 'montant' => 100]],
            $user,
        );
        $engagement->update(['statut' => 'vise', 'credits_reserves' => true, 'activite_id' => $activite->id]);
        $liq = Liquidation::create(['numero' => 'LIQ-D5-E', 'engagement_id' => $engagement->id, 'montant' => 100, 'statut' => 'valide', 'compteurs_appliques' => true]);
        $ord = Ordonnancement::create(['numero' => 'ORD-D5-E', 'liquidation_id' => $liq->id, 'montant' => 100, 'statut' => 'valide', 'compteurs_appliques' => true]);
        Paiement::create([
            'numero' => 'PAY-D5-E', 'ordonnancement_id' => $ord->id, 'montant' => 100,
            'devise' => 'XAF', 'taux_change' => 1, 'montant_xaf' => 100,
            'mode_paiement' => 'virement', 'statut' => 'valide', 'compteurs_appliques' => true,
        ]);

        $types = collect(app(DepenseAuditService::class)->executer($this->exercice->id)['anomalies'])->pluck('type');
        $this->assertTrue($types->contains('paiement_sans_ecriture_comptable'));
    }

    public function test_commande_audit_avec_seuil_elevee(): void
    {
        $this->artisan('depense:audit', ['--exercice' => 2026, '--seuil' => 'elevee'])
            ->expectsOutputToContain('Score conformité')
            ->assertSuccessful();
    }

    public function test_parcours_e2e_http_besoin_vers_paiement(): void
    {
        $u = $this->acteurs();
        [$activite, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-D5-E2E', 10_000);

        // 1. Expression du besoin
        $this->actingAs($u['chef'])
            ->post(route('besoins.store'), [
                'exercice_id' => $this->exercice->id,
                'activite_id' => $activite->id,
                'ligne_budgetaire_id' => $ligne->id,
                'objet' => 'Parcours E2E D5',
                'montant_estime' => 750,
                'priorite' => 'moyenne',
            ])
            ->assertRedirect();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->assertNotNull($besoin);

        $this->actingAs($u['chef'])->post(route('besoins.soumettre', $besoin))->assertRedirect();
        $this->actingAs($u['chef'])->post(route('besoins.agir', $besoin), ['decision' => 'valider'])->assertRedirect();
        $this->actingAs($u['directeur'])->post(route('besoins.agir', $besoin->fresh()), ['decision' => 'valider'])->assertRedirect();
        $this->actingAs($u['cf'])->post(route('besoins.agir', $besoin->fresh()), ['decision' => 'valider'])->assertRedirect();
        $this->assertSame('valide', $besoin->fresh()->statut);

        // 2. Engagement
        $this->actingAs($u['gestionnaire'])->post(route('besoins.convertir', $besoin))->assertRedirect();
        $this->actingAs($u['gestionnaire'])->post(route('engagements.store'), [
            'exercice_id' => $this->exercice->id,
            'expression_besoin_id' => $besoin->id,
            'type' => 'simple',
            'objet' => 'Parcours E2E D5',
            'imputations' => [['ligne_budgetaire_id' => $ligne->id, 'montant' => 750]],
        ])->assertRedirect();

        $engagement = Engagement::latest('id')->first();
        $this->actingAs($u['gestionnaire'])->post(route('engagements.soumettre', $engagement))->assertRedirect();
        $this->actingAs($u['cf'])->post(route('engagements.agir', $engagement->fresh()), [
            'decision' => 'valider',
        ])->assertRedirect();
        $this->assertSame('vise', $engagement->fresh()->statut);

        // 3. Liquidation
        $this->actingAs($u['gestionnaire'])->post(route('depense.store', 'liquidation'), [
            'parent' => $engagement->id,
            'montant' => 750,
            'service_fait' => 'Prestation reçue conformément',
        ])->assertRedirect();
        $liq = Liquidation::latest('id')->first();
        $this->actingAs($u['gestionnaire'])->post(route('depense.soumettre', ['liquidation', $liq->id]))->assertRedirect();
        $this->actingAs($u['cf'])->post(route('depense.agir', ['liquidation', $liq->fresh()->id]), ['decision' => 'valider'])->assertRedirect();

        // 4. Ordonnancement
        $this->actingAs($u['gestionnaire'])->post(route('depense.store', 'ordonnancement'), [
            'parent' => $liq->fresh()->id,
        ])->assertRedirect();
        $ord = Ordonnancement::latest('id')->first();
        $this->actingAs($u['gestionnaire'])->post(route('depense.soumettre', ['ordonnancement', $ord->id]))->assertRedirect();
        $this->actingAs($u['sg'])->post(route('depense.agir', ['ordonnancement', $ord->fresh()->id]), ['decision' => 'valider'])->assertRedirect();

        // 5. Paiement
        $ord = $ord->fresh();
        $this->assertSame('valide', $ord->statut, 'Ordonnancement non validé avant paiement');

        $respPay = $this->actingAs($u['gestionnaire'])->post(route('depense.store', 'paiement'), [
            'parent' => $ord->id,
            'mode_paiement' => 'virement',
            'devise' => 'XAF',
            'taux_change' => 1,
            'reference_bancaire' => 'REF-E2E-D5',
        ]);
        $respPay->assertRedirect()->assertSessionHasNoErrors();
        $pay = Paiement::where('ordonnancement_id', $ord->id)->first();
        $this->assertNotNull($pay, 'Paiement non créé — session: '.json_encode(session()->all()));
        $this->actingAs($u['gestionnaire'])->post(route('depense.soumettre', ['paiement', $pay->id]))->assertRedirect();
        $this->actingAs($u['comptable'])->post(route('depense.agir', ['paiement', $pay->fresh()->id]), ['decision' => 'valider'])->assertRedirect();

        $pay = $pay->fresh();
        $ligne = $ligne->fresh();
        $this->assertSame('valide', $pay->statut);
        $this->assertEqualsWithDelta(750, (float) $ligne->montant_paye, 0.05);
        $this->assertTrue(EcritureComptable::where('paiement_id', $pay->id)->exists());

        // 6. Dossier unifié
        $this->actingAs($u['gestionnaire'])
            ->get(route('depense.dossier.engagement', $engagement))
            ->assertOk()
            ->assertSee('Dossier dépense unifié')
            ->assertSee($besoin->numero);

        // 7. Audit sans anomalie grave
        $rapport = app(DepenseAuditService::class)->executer($this->exercice->id);
        $this->assertFalse(
            app(DepenseAuditService::class)->depasseSeuil($rapport['anomalies'], 'elevee'),
            'Anomalies élevées : '.json_encode(collect($rapport['anomalies'])->where('gravite', 'elevee')->values())
        );
    }

    public function test_documentation_utilisateur_existe(): void
    {
        $path = base_path('docs/module-chaine-depense.md');
        $this->assertFileExists($path);
        $contenu = file_get_contents($path);
        $this->assertStringContainsString('Besoin', $contenu);
        $this->assertStringContainsString('depense:audit', $contenu);
        $this->assertStringContainsString('Rapprochement', $contenu);
    }
}
