<?php

namespace Tests\Feature;

use App\Enums\Ged\NiveauConfidentialite;
use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\ExpressionBesoin;
use App\Models\Ged\GedDocument;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Structure;
use App\Models\User;
use App\Services\Ged\GedSearchService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase A4 — périmètre structure sur Besoins, PAP et GED. */
class ReferentielAdministratifPhaseA4Test extends TestCase
{
    use RefreshDatabase;

    private Structure $depAutorise;

    private Structure $depRefuse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->depAutorise = Structure::create([
            'code' => 'DEP-A4-OK',
            'libelle' => 'Departement autorise',
            'type' => 'departement',
            'actif' => true,
        ]);
        $this->depRefuse = Structure::create([
            'code' => 'DEP-A4-KO',
            'libelle' => 'Departement refuse',
            'type' => 'departement',
            'actif' => true,
        ]);
    }

    private function utilisateurPerimetre(): User
    {
        $user = User::factory()->create(['actif' => true])->assignRole('chef_service');
        $user->accessScopes()->create(['type' => 'departement', 'scope_id' => $this->depAutorise->id]);

        return $user;
    }

    public function test_besoin_hors_perimetre_est_invisible_et_interdit(): void
    {
        $exercice = Exercice::factory()->create();
        $besoinOk = ExpressionBesoin::create([
            'numero' => 'EB-OK-001',
            'exercice_id' => $exercice->id,
            'structure_id' => $this->depAutorise->id,
            'demandeur_id' => User::factory()->create()->id,
            'objet' => 'Besoin autorise',
            'montant_estime' => 1000,
            'statut' => 'brouillon',
        ]);
        $besoinKo = ExpressionBesoin::create([
            'numero' => 'EB-KO-001',
            'exercice_id' => $exercice->id,
            'structure_id' => $this->depRefuse->id,
            'demandeur_id' => User::factory()->create()->id,
            'objet' => 'Besoin refuse',
            'montant_estime' => 2000,
            'statut' => 'brouillon',
        ]);

        $user = $this->utilisateurPerimetre();

        $this->actingAs($user)->get(route('besoins.show', $besoinOk))->assertOk();
        $this->actingAs($user)->get(route('besoins.show', $besoinKo))->assertForbidden();
        $this->actingAs($user)->get(route('besoins.index'))
            ->assertOk()
            ->assertSee('Besoin autorise')
            ->assertDontSee('Besoin refuse');
    }

    public function test_creation_besoin_refuse_structure_hors_perimetre(): void
    {
        $exercice = Exercice::factory()->create();
        $user = $this->utilisateurPerimetre();
        $chaine = $this->chaineRbm($exercice);
        $activite = $this->creerActivite($chaine, 'ACT-POST-A4', $this->depAutorise->id);
        $rubrique = \App\Models\BudgetRubrique::firstOrCreate(
            ['code' => '661A4'],
            ['paragraphe_id' => \App\Models\BudgetParagraphe::firstOrCreate(
                ['code' => '661A4P'],
                ['article_id' => \App\Models\BudgetArticle::firstOrCreate(
                    ['code' => '66A4'],
                    ['titre_id' => \App\Models\BudgetTitre::firstOrCreate(['code' => '1A4'], ['sens' => 'depense', 'intitule' => 'T'])->id, 'intitule' => 'A']
                )->id, 'intitule' => 'P']
            )->id, 'intitule' => 'R']
        );
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => 'LB-A4-POST',
            'montant_ouvert' => 100000,
            'activite_id' => $activite->id,
        ]);
        $activite->update(['ligne_budgetaire_id' => $ligne->id]);

        $this->actingAs($user)->post(route('besoins.store'), [
            'exercice_id' => $exercice->id,
            'structure_id' => $this->depRefuse->id,
            'activite_id' => $activite->id,
            'objet' => 'Tentative hors perimetre',
            'montant_estime' => 500,
        ])->assertSessionHasErrors('structure_id');
    }

    public function test_arbre_rbm_masque_activites_hors_perimetre(): void
    {
        $exercice = Exercice::factory()->create();
        $chaine = $this->chaineRbm($exercice);
        $activiteOk = $this->creerActivite($chaine, 'ACT-OK-A4', $this->depAutorise->id);
        $activiteKo = $this->creerActivite($chaine, 'ACT-KO-A4', $this->depRefuse->id);

        $user = $this->utilisateurPerimetre();
        $user->givePermissionTo('rbm.voir');

        $this->actingAs($user)->get(route('rbm.arbre', $exercice))
            ->assertOk()
            ->assertSee($activiteOk->code)
            ->assertDontSee($activiteKo->code);

        $this->actingAs($user)->get(route('rbm.edit', [
            'niveau' => 'activite',
            'id' => $activiteKo->id,
            'exercice' => $exercice->id,
        ]))->assertForbidden();
    }

    public function test_ged_masque_documents_hors_perimetre(): void
    {
        $user = $this->utilisateurPerimetre();
        $autre = User::factory()->create(['actif' => true]);

        $docOk = GedDocument::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'GED-A4-OK',
            'titre' => 'Document autorise A4',
            'chemin_fichier' => 'GED/A4/ok.pdf',
            'nom_original' => 'ok.pdf',
            'structure_id' => $this->depAutorise->id,
            'niveau_confidentialite' => NiveauConfidentialite::Interne,
            'created_by' => $autre->id,
            'date_depot' => now(),
        ]);
        GedDocument::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'GED-A4-KO',
            'titre' => 'Document refuse A4',
            'chemin_fichier' => 'GED/A4/ko.pdf',
            'nom_original' => 'ko.pdf',
            'structure_id' => $this->depRefuse->id,
            'niveau_confidentialite' => NiveauConfidentialite::Interne,
            'created_by' => $autre->id,
            'date_depot' => now(),
        ]);

        $resultats = app(GedSearchService::class)->rechercher([], $user);

        $this->assertTrue(collect($resultats->items())->contains(fn ($d) => $d->id === $docOk->id));
        $this->assertFalse(collect($resultats->items())->contains(fn ($d) => $d->titre === 'Document refuse A4'));
    }

    /** @return array{pilier: Pilier, axe: Axe, produit: Produit, sous_produit: SousProduit} */
    private function chaineRbm(Exercice $exercice): array
    {
        $pilier = Pilier::create([
            'exercice_id' => $exercice->id,
            'code' => 'P-A4',
            'intitule' => 'Pilier A4',
            'statut' => 'actif',
        ]);
        $axe = Axe::create([
            'pilier_id' => $pilier->id,
            'code' => 'A-A4',
            'intitule' => 'Axe A4',
            'statut' => 'actif',
        ]);
        $produit = Produit::create([
            'axe_id' => $axe->id,
            'code' => 'PR-A4',
            'intitule' => 'Produit A4',
            'statut' => 'actif',
        ]);
        $sousProduit = SousProduit::create([
            'produit_id' => $produit->id,
            'code' => 'SP-A4',
            'intitule' => 'Sous-produit A4',
            'statut' => 'actif',
        ]);

        return compact('pilier', 'axe', 'produit', 'sousProduit');
    }

    /** @param  array{pilier: Pilier, axe: Axe, produit: Produit, sous_produit: SousProduit}  $chaine */
    private function creerActivite(array $chaine, string $code, int $structureId): Activite
    {
        return Activite::create([
            'sous_produit_id' => $chaine['sousProduit']->id,
            'produit_id' => $chaine['produit']->id,
            'structure_id' => $structureId,
            'code' => $code,
            'intitule' => "Activite {$code}",
            'statut' => 'actif',
            'montant_ceeac' => 0,
            'montant_ptf' => 0,
            'montant_total' => 0,
        ]);
    }
}
