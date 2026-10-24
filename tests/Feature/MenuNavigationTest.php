<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Navigation\MenuService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** P2-7 — menu sidebar centralisé. */
class MenuNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['mfa.enabled' => false]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole($role);
    }

    public function test_administrateur_voit_le_tableau_de_bord_et_la_ged(): void
    {
        $items = app(MenuService::class)->build($this->user('administrateur'));

        $labels = collect($items)->pluck('label');
        $this->assertTrue($labels->contains('Tableau de bord'));
        $this->assertTrue($labels->contains('Gestion documentaire (GED)'));
        $this->assertTrue($labels->contains('Documents'));
    }

    public function test_lecteur_ne_voit_pas_les_ecrans_administration(): void
    {
        $labels = collect(app(MenuService::class)->build($this->user('lecteur')))->pluck('label');

        $this->assertFalse($labels->contains('Circuits workflow'));
        $this->assertFalse($labels->contains('Paramètres GED'));
        $this->assertFalse($labels->contains('Modèles e-mail'));
    }

    public function test_point_focal_a_un_perimetre_restreint(): void
    {
        $admin = app(MenuService::class)->build($this->user('administrateur'));
        $focal = app(MenuService::class)->build($this->user('point_focal'));

        $this->assertGreaterThan(count($focal), count($admin));
        $this->assertFalse(collect($focal)->pluck('label')->contains('Budget & Crédits'));
    }

    public function test_sections_sans_liens_visibles_sont_masquees(): void
    {
        $items = app(MenuService::class)->build($this->user('comptable'));
        $types = collect($items)->pluck('type');

        foreach ($types as $i => $type) {
            if ($type === 'section') {
                $this->assertSame('link', $types[$i + 1] ?? null, 'Section sans lien suivant');
            }
        }
    }

    public function test_layout_rend_le_menu_configurable(): void
    {
        $this->actingAs($this->user('lecteur'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tableau de bord');
    }

    public function test_menu_spa_annote_les_parcours_migres(): void
    {
        $items = app(MenuService::class)->buildSpa($this->user('administrateur'));
        $accueil = collect($items)->firstWhere('route', 'dashboard');

        $this->assertIsArray($accueil);
        $this->assertTrue($accueil['migre']);
        $this->assertSame('/tableau-bord', $accueil['path']);

        $chaine = collect($items)->firstWhere('label', 'Chaine de la depense');
        $this->assertIsArray($chaine);
        $liquidations = collect($chaine['children'] ?? [])->firstWhere('label', 'Liquidations');
        $this->assertIsArray($liquidations);
        $this->assertTrue($liquidations['migre']);
        $this->assertSame('/liquidations', $liquidations['path']);
        $rapprochement = collect($chaine['children'] ?? [])->firstWhere('label', 'Rapprochement');
        $this->assertIsArray($rapprochement);
        $this->assertTrue($rapprochement['migre']);
        $this->assertSame('/rapprochements', $rapprochement['path']);
        $marchesGroupe = collect($items)->firstWhere('label', 'Marches et contrats');
        $this->assertIsArray($marchesGroupe);
        $ppm = collect($marchesGroupe['children'] ?? [])->firstWhere('label', 'Marches / PPM');
        $this->assertIsArray($ppm);
        $this->assertTrue($ppm['migre']);
        $this->assertSame('/marches', $ppm['path']);
        $contrats = collect($marchesGroupe['children'] ?? [])->firstWhere('label', 'Contrats');
        $this->assertIsArray($contrats);
        $this->assertTrue($contrats['migre']);
        $this->assertSame('/contrats', $contrats['path']);
        $suiviPerf = collect($items)->firstWhere('label', 'Suivi et performance');
        $this->assertIsArray($suiviPerf);
        $se = collect($suiviPerf['children'] ?? [])->firstWhere('label', 'Suivi-evaluation');
        $this->assertIsArray($se);
        $this->assertTrue($se['migre']);
        $this->assertSame('/suivi-evaluation', $se['path']);
        $campagnes = collect($suiviPerf['children'] ?? [])->firstWhere('label', 'Campagnes de suivi');
        $this->assertIsArray($campagnes);
        $this->assertTrue($campagnes['migre']);
        $this->assertSame('/suivi', $campagnes['path']);
        $reco = collect($suiviPerf['children'] ?? [])->firstWhere('label', 'Recommandations');
        $this->assertIsArray($reco);
        $this->assertTrue($reco['migre']);
        $this->assertSame('/recommandations', $reco['path']);

        $gestion = collect($items)->firstWhere('label', 'Gestion budgetaire');
        $this->assertIsArray($gestion);
        $enveloppes = collect($gestion['children'] ?? [])->firstWhere('label', 'Preparation et enveloppes');
        $this->assertIsArray($enveloppes);
        $this->assertTrue($enveloppes['migre']);
        $this->assertSame('/budgets', $enveloppes['path']);
        $sf = collect($gestion['children'] ?? [])->firstWhere('label', 'Suivi financier');
        $this->assertIsArray($sf);
        $this->assertTrue($sf['migre']);
        $this->assertSame('/suivi-financier', $sf['path']);

        $papGroupe = collect($items)->firstWhere('label', 'Plan annuel de performance');
        $this->assertIsArray($papGroupe);
        $elab = collect($papGroupe['children'] ?? [])->firstWhere('label', 'Elaboration et workflow PAP');
        $this->assertIsArray($elab);
        $this->assertTrue($elab['migre']);
        $this->assertSame('/paps', $elab['path']);
        $perf = collect($papGroupe['children'] ?? [])->firstWhere('label', 'Performance PAP');
        $this->assertIsArray($perf);
        $this->assertTrue($perf['migre']);
        $this->assertSame('/paps/pilotage', $perf['path']);

        $recettes = collect($items)->firstWhere('label', 'Gestion des recettes');
        $this->assertIsArray($recettes);
        $previsions = collect($recettes['children'] ?? [])->firstWhere('label', 'Previsions et recettes');
        $this->assertIsArray($previsions);
        $this->assertTrue($previsions['migre']);
        $this->assertSame('/recettes', $previsions['path']);
        $enc = collect($recettes['children'] ?? [])->firstWhere('label', 'Encaissements');
        $this->assertIsArray($enc);
        $this->assertTrue($enc['migre']);
        $this->assertSame('/encaissements', $enc['path']);
        $appels = collect($recettes['children'] ?? [])->firstWhere('label', 'Contributions et appels de fonds');
        $this->assertIsArray($appels);
        $this->assertTrue($appels['migre']);
        $this->assertSame('/appels-fonds', $appels['path']);
        $etats = collect($recettes['children'] ?? [])->firstWhere('label', 'Etats des recettes');
        $this->assertIsArray($etats);
        $this->assertTrue($etats['migre']);
        $this->assertSame('/recettes/etats', $etats['path']);
        $rbm = collect($items)->firstWhere('label', 'RBM / GAR');
        $this->assertIsArray($rbm);
        $chaineRbm = collect($rbm['children'] ?? [])->firstWhere('label', 'Chaine RBM / GAR');
        $this->assertIsArray($chaineRbm);
        $this->assertTrue($chaineRbm['migre']);
        $this->assertSame('/rbm', $chaineRbm['path']);
        $ged = collect($items)->firstWhere('label', 'Gestion documentaire');
        $this->assertIsArray($ged);
        $gedDash = collect($ged['children'] ?? [])->firstWhere('label', 'Tableau de bord GED');
        $this->assertIsArray($gedDash);
        $this->assertTrue($gedDash['migre']);
        $this->assertSame('/ged', $gedDash['path']);
        $gedDocs = collect($ged['children'] ?? [])->firstWhere('label', 'Documents');
        $this->assertIsArray($gedDocs);
        $this->assertTrue($gedDocs['migre']);
        $this->assertSame('/ged/documents', $gedDocs['path']);
        $gedDepot = collect($ged['children'] ?? [])->firstWhere('label', 'Depot documentaire');
        $this->assertIsArray($gedDepot);
        $this->assertTrue($gedDepot['migre']);
        $this->assertSame('/ged/depot', $gedDepot['path']);
        $gedSig = collect($ged['children'] ?? [])->firstWhere('label', 'Signatures');
        $this->assertIsArray($gedSig);
        $this->assertTrue($gedSig['migre']);
        $this->assertSame('/ged/signatures', $gedSig['path']);
        $gedRecherche = collect($ged['children'] ?? [])->firstWhere('label', 'Recherche avancee');
        $this->assertIsArray($gedRecherche);
        $this->assertTrue($gedRecherche['migre']);
        $this->assertSame('/ged/recherche', $gedRecherche['path']);
        $gedClassement = collect($ged['children'] ?? [])->firstWhere('label', 'Classement');
        $this->assertIsArray($gedClassement);
        $this->assertTrue($gedClassement['migre']);
        $this->assertSame('/ged/classement', $gedClassement['path']);
        $gedArchives = collect($ged['children'] ?? [])->firstWhere('label', 'Archives');
        $this->assertIsArray($gedArchives);
        $this->assertTrue($gedArchives['migre']);
        $this->assertSame('/ged/archives', $gedArchives['path']);
        $gedJournal = collect($ged['children'] ?? [])->firstWhere('label', 'Journal documentaire');
        $this->assertIsArray($gedJournal);
        $this->assertTrue($gedJournal['migre']);
        $this->assertSame('/ged/journal', $gedJournal['path']);
        $connaissances = collect($items)->firstWhere('label', 'Gestion des connaissances');
        $this->assertIsArray($connaissances);
        $gedWiki = collect($connaissances['children'] ?? [])->firstWhere('label', 'Wiki et procedures');
        $this->assertIsArray($gedWiki);
        $this->assertTrue($gedWiki['migre']);
        $this->assertSame('/ged/wiki', $gedWiki['path']);
        $tableaux = collect($items)->firstWhere('label', 'Tableaux de bord');
        $this->assertIsArray($tableaux);
        $executif = collect($tableaux['children'] ?? [])->firstWhere('label', 'Executif');
        $this->assertIsArray($executif);
        $this->assertTrue($executif['migre']);
        $this->assertSame('/reporting/cockpit', $executif['path']);
        $reporting = collect($items)->firstWhere('label', 'Reporting et analyse');
        $this->assertIsArray($reporting);
        $rapports = collect($reporting['children'] ?? [])->firstWhere('label', 'Rapports et reporting');
        $this->assertIsArray($rapports);
        $this->assertTrue($rapports['migre']);
        $this->assertSame('/reporting', $rapports['path']);
        $exports = collect($reporting['children'] ?? [])->firstWhere('label', 'Centre d exports');
        $this->assertIsArray($exports);
        $this->assertTrue($exports['migre']);
        $this->assertSame('/reporting/rapports', $exports['path']);
        $modeles = collect($reporting['children'] ?? [])->firstWhere('label', 'Rapports personnalises');
        $this->assertIsArray($modeles);
        $this->assertTrue($modeles['migre']);
        $this->assertSame('/reporting/modeles', $modeles['path']);
        $wf = collect($items)->firstWhere('label', 'Workflow et validation');
        $this->assertIsArray($wf);
        $perfWf = collect($wf['children'] ?? [])->firstWhere('label', 'Performance workflow');
        $this->assertIsArray($perfWf);
        $this->assertTrue($perfWf['migre']);
        $this->assertSame('/workflow', $perfWf['path']);
        $corbeille = collect($wf['children'] ?? [])->firstWhere('label', 'Corbeille validations');
        $this->assertIsArray($corbeille);
        $this->assertTrue($corbeille['migre']);
        $this->assertSame('/workflow/corbeille', $corbeille['path']);
        $circuits = collect($wf['children'] ?? [])->firstWhere('label', 'Circuits workflow');
        $this->assertIsArray($circuits);
        $this->assertTrue($circuits['migre']);
        $this->assertSame('/workflow/circuits', $circuits['path']);
        $auditWf = collect($wf['children'] ?? [])->firstWhere('label', 'Audit workflow');
        $this->assertIsArray($auditWf);
        $this->assertTrue($auditWf['migre']);
        $this->assertSame('/workflow/audit', $auditWf['path']);
        $notif = collect($items)->firstWhere('label', 'Notifications et alertes');
        $this->assertIsArray($notif);
        $centre = collect($notif['children'] ?? [])->firstWhere('label', 'Notifications');
        $this->assertIsArray($centre);
        $this->assertTrue($centre['migre']);
        $this->assertSame('/notifications', $centre['path']);
        $alertes = collect($notif['children'] ?? [])->firstWhere('label', 'Alertes');
        $this->assertIsArray($alertes);
        $this->assertTrue($alertes['migre']);
        $this->assertSame('/notifications/alertes', $alertes['path']);
        $prefs = collect($notif['children'] ?? [])->firstWhere('label', 'Preferences notifications');
        $this->assertIsArray($prefs);
        $this->assertTrue($prefs['migre']);
        $this->assertSame('/notifications/preferences', $prefs['path']);
        $modelesMail = collect($notif['children'] ?? [])->firstWhere('label', 'Modeles e-mail');
        $this->assertIsArray($modelesMail);
        $this->assertTrue($modelesMail['migre']);
        $this->assertSame('/notifications/templates', $modelesMail['path']);
        $interop = collect($items)->firstWhere('label', 'Interoperabilite et API');
        $this->assertIsArray($interop);
        $hubInterop = collect($interop['children'] ?? [])->firstWhere('label', 'Hub interoperabilite');
        $this->assertIsArray($hubInterop);
        $this->assertTrue($hubInterop['migre']);
        $this->assertSame('/interoperabilite', $hubInterop['path']);
    }

    public function test_toutes_les_routes_du_menu_existent(): void
    {
        foreach (config('menu.items', []) as $entry) {
            if (($entry['type'] ?? 'link') !== 'link') {
                continue;
            }
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($entry['route']),
                "Route menu introuvable : {$entry['route']}"
            );
        }
    }
}
