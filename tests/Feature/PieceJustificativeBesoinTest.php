<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\PieceJustificative;
use App\Models\User;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Pièces justificatives d'une expression de besoin : jointes depuis la page de modification par son rédacteur. */
class PieceJustificativeBesoinTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private ExpressionBesoin $besoin;

    private User $redacteur;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        Storage::fake('local');
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class]);
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        [$activite, $ligne] = $this->activiteEtLigne($exercice, 'LB-PJ', 100_000);
        $this->redacteur = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('chef_service');
        $this->besoin = ExpressionBesoin::create([
            'numero' => 'EB-PJ-1', 'exercice_id' => $exercice->id, 'activite_id' => $activite->id, 'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $this->redacteur->id, 'objet' => 'Besoin avec pièces', 'montant_estime' => 500, 'priorite' => 'moyenne', 'statut' => 'brouillon',
        ]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('devis.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    public function test_page_de_modification_propose_l_ajout_de_pieces(): void
    {
        $this->actingAs($this->redacteur)->get(route('besoins.edit', $this->besoin))->assertOk()
            ->assertSee('Pièces justificatives')->assertSee('name="fichier"', false);
    }

    public function test_le_redacteur_joint_telecharge_et_retire_sa_piece(): void
    {
        $this->actingAs($this->redacteur)->from(route('besoins.edit', $this->besoin))
            ->post(route('pieces.store', ['besoin', $this->besoin->id]), ['fichier' => $this->pdf(), 'categorie' => 'devis'])
            ->assertRedirect(route('besoins.edit', $this->besoin))->assertSessionHasNoErrors();

        $piece = PieceJustificative::firstOrFail();
        $this->assertSame('devis', $piece->categorie);
        $this->actingAs($this->redacteur)->get(route('pieces.download', $piece))->assertOk();
        $this->actingAs($this->redacteur)->delete(route('pieces.destroy', $piece))->assertRedirect();
        $this->assertSame(0, PieceJustificative::count());
    }

    private function besoinDuPointFocal(): array
    {
        $pointFocal = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('point_focal');
        $besoin = $this->besoin->replicate()->fill(['numero' => 'EB-PJ-PF', 'demandeur_id' => $pointFocal->id]);
        $besoin->save();

        return [$pointFocal, $besoin];
    }

    public function test_le_point_focal_demandeur_joint_et_retire_ses_pieces_depuis_la_fiche(): void
    {
        [$pointFocal, $besoin] = $this->besoinDuPointFocal();

        $this->actingAs($pointFocal)->get(route('besoins.show', $besoin))->assertOk()->assertSee('name="fichier"', false);
        $this->actingAs($pointFocal)->from(route('besoins.show', $besoin))
            ->post(route('pieces.store', ['besoin', $besoin->id]), ['fichier' => $this->pdf(), 'categorie' => 'devis'])
            ->assertRedirect(route('besoins.show', $besoin))->assertSessionHasNoErrors();

        $piece = PieceJustificative::firstOrFail();
        $this->assertSame($pointFocal->id, (int) $piece->televerse_par);
        $this->actingAs($pointFocal)->delete(route('pieces.destroy', $piece))->assertRedirect();
        $this->assertSame(0, PieceJustificative::count());
    }

    public function test_le_point_focal_demandeur_joint_ses_pieces_par_l_api(): void
    {
        [$pointFocal, $besoin] = $this->besoinDuPointFocal();
        \Laravel\Sanctum\Sanctum::actingAs($pointFocal, ['*']);

        $this->post(route('api.pieces.store', ['besoin', $besoin->id]), ['fichier' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(1, PieceJustificative::count());
    }

    public function test_le_point_focal_ne_joint_plus_de_piece_une_fois_l_eb_soumise(): void
    {
        [$pointFocal, $besoin] = $this->besoinDuPointFocal();
        $besoin->update(['statut' => 'soumis']);

        $this->actingAs($pointFocal)->post(route('pieces.store', ['besoin', $besoin->id]), ['fichier' => $this->pdf()])->assertForbidden();
        $this->assertSame(0, PieceJustificative::count());
    }

    public function test_sans_droit_sur_le_dossier_ni_droit_ged_l_ajout_est_refuse(): void
    {
        $lecteur = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('point_focal');

        $this->actingAs($lecteur)->post(route('pieces.store', ['besoin', $this->besoin->id]), ['fichier' => $this->pdf()])->assertForbidden();
        $this->assertSame(0, PieceJustificative::count());
    }

    public function test_piece_de_l_eb_visible_sur_l_engagement_et_la_liquidation(): void
    {
        $this->actingAs($this->redacteur)->post(route('pieces.store', ['besoin', $this->besoin->id]), ['fichier' => $this->pdf(), 'categorie' => 'devis']);
        $engagement = \App\Models\Engagement::create([
            'exercice_id' => $this->besoin->exercice_id, 'expression_besoin_id' => $this->besoin->id, 'numero' => 'ENG-PJ-1',
            'type' => 'simple', 'objet' => 'ENG', 'montant_total' => 500, 'statut' => 'vise', 'credits_reserves' => true,
        ]);
        $liquidation = \App\Models\Liquidation::create(['numero' => 'LIQ-PJ-1', 'engagement_id' => $engagement->id, 'montant' => 500, 'statut' => 'brouillon']);
        $admin = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('administrateur');

        $this->actingAs($admin)->get(route('engagements.show', $engagement))->assertOk()
            ->assertSee('Expression de besoin EB-PJ-1')->assertSee('devis.pdf');
        $this->assertSame(['Engagement', 'Expression de besoin'],
            array_column(\App\Http\Controllers\PieceController::dossiersAmont($liquidation), 0), 'Chaîne amont complète depuis la liquidation.');
    }

    public function test_dossier_soumis_le_redacteur_ne_peut_plus_retirer_sa_piece(): void
    {
        $this->actingAs($this->redacteur)->post(route('pieces.store', ['besoin', $this->besoin->id]), ['fichier' => $this->pdf()]);
        $this->besoin->update(['statut' => 'soumis']);

        $this->actingAs($this->redacteur)->delete(route('pieces.destroy', PieceJustificative::firstOrFail()))->assertForbidden();
    }
}
