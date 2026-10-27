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
    /** Acteur (clé de acteurs()) par rôle d'étape ; chef_service → chef_visa (l'initiateur ne valide pas). */
    private const ROLES_ACTEURS = [
        'chef_visa' => 'chef_service', 'directeur' => 'directeur', 'cf' => 'controleur_financier',
        'gestionnaire' => 'gestionnaire_budgetaire', 'sg' => 'secretaire_general', 'comptable' => 'comptable', 'agent' => 'agent_comptable',
    ];

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
            'agent' => $this->user('agent_comptable'),
            'chef_visa' => $this->user('chef_service'),
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
                'activite_responsable_id' => $u['chef']->id,
                'montant_estime' => 750,
                'priorite' => 'moyenne',
            ])
            ->assertRedirect();

        $besoin = ExpressionBesoin::latest('id')->first();
        $this->assertNotNull($besoin);

        $this->actingAs($u['chef'])->post(route('besoins.soumettre', $besoin))->assertRedirect();
        // Le circuit dépend de l'EB (BESOIN, BESOIN_PAP, BESOIN_PAP_APPUI…) : chaque étape est validée par un
        // porteur du rôle qu'elle requiert, jusqu'à la validation du besoin.
        for ($i = 0; $i < 6 && ($instance = $besoin->fresh()->workflowActif)?->statut === 'en_cours'; $i++) {
            $role = $instance->etapeCourante()?->role;
            $this->workflow->valider($instance, $u[array_search($role, self::ROLES_ACTEURS, true) ?: ''] ?? $this->user($role));
        }
        $this->assertSame('valide', $besoin->fresh()->statut);

        $engagement = Engagement::query()->where('expression_besoin_id', $besoin->id)->first();
        if (! $engagement) {
            $this->actingAs($u['gestionnaire'])->post(route('besoins.convertir', $besoin))->assertRedirect();
            $this->actingAs($u['gestionnaire'])->post(route('engagements.store'), [
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'type' => 'simple',
                'objet' => 'Parcours E2E D5',
                'imputations' => [['ligne_budgetaire_id' => $ligne->id, 'montant' => 750]],
            ])->assertRedirect();
            $engagement = Engagement::latest('id')->first();
        }
        if ($engagement->estModifiable()) {
            $this->actingAs($u['gestionnaire'])->post(route('engagements.soumettre', $engagement))->assertRedirect();
        }
        if ($engagement->fresh()->statut !== 'vise') {
            $this->viserEngagement($engagement);
        }
        $this->assertSame('vise', $engagement->fresh()->statut);

        $liq = Liquidation::query()->where('engagement_id', $engagement->id)->first();
        if (! $liq) {
            $this->actingAs($u['gestionnaire'])->post(route('depense.store', 'liquidation'), [
                'parent' => $engagement->id,
                'montant' => 750,
                'service_fait' => 'Prestation reçue conformément',
            ])->assertRedirect();
            $liq = Liquidation::latest('id')->first();
        } elseif (blank($liq->service_fait)) {
            $liq->update(['service_fait' => 'Prestation reçue conformément']);
        }
        if ($liq->statut === 'brouillon') {
            $this->actingAs($u['gestionnaire'])->post(route('depense.soumettre', ['liquidation', $liq->id]))->assertRedirect();
        }
        if ($liq->fresh()->statut !== 'valide') {
            $this->viserLiquidation($liq);
        }

        $ord = Ordonnancement::query()->where('liquidation_id', $liq->id)->first();
        if (! $ord) {
            $this->actingAs($u['gestionnaire'])->post(route('depense.store', 'ordonnancement'), [
                'parent' => $liq->fresh()->id,
            ])->assertRedirect();
            $ord = Ordonnancement::latest('id')->first();
        }
        if ($ord->statut === 'brouillon') {
            $this->actingAs($u['gestionnaire'])->post(route('depense.soumettre', ['ordonnancement', $ord->id]))->assertRedirect();
        }
        if ($ord->fresh()->statut !== 'valide') {
            $this->viserOrdonnancement($ord);
        }

        // 5. Paiement
        $ord = $ord->fresh();
        $this->assertSame('valide', $ord->statut, 'Ordonnancement non validé avant paiement');

        $pay = Paiement::query()->where('ordonnancement_id', $ord->id)->first();
        if (! $pay) {
            $respPay = $this->actingAs($u['gestionnaire'])->post(route('depense.store', 'paiement'), [
                'parent' => $ord->id,
                'mode_paiement' => 'virement',
                'devise' => 'XAF',
                'taux_change' => 1,
                'reference_bancaire' => 'REF-E2E-D5',
            ]);
            $respPay->assertRedirect()->assertSessionHasNoErrors();
            $pay = Paiement::where('ordonnancement_id', $ord->id)->first();
        }
        $this->assertNotNull($pay, 'Paiement non créé — session: '.json_encode(session()->all()));
        if ($pay->statut === 'brouillon') {
            $this->actingAs($u['gestionnaire'])->post(route('depense.soumettre', ['paiement', $pay->id]))->assertRedirect();
        }
        if ($pay->fresh()->statut !== 'valide') {
            $this->viserPaiement($pay);
        }

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
