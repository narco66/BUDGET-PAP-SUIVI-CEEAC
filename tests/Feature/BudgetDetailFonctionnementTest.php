<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaireDetail;
use App\Models\User;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/** Détail à 5 chiffres du budget de fonctionnement : import et choix dans « Activité rattachée » d'une EB. */
class BudgetDetailFonctionnementTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
    }

    private function importer(): \App\Models\LigneBudgetaire
    {
        [, $ligne] = $this->activiteEtLigne($this->exercice, 'LB-2026-6111', 620_000_000);
        $ligne->update(['activite_id' => null]);
        $fichier = tempnam(sys_get_temp_dir(), 'det');
        file_put_contents($fichier, "code;intitule;montant\n61111;Voyages et déplacements;250000000\n61112;Frais de transports déménagement;370000000\n");
        $this->artisan('budget:importer-detail', ['fichier' => $fichier])->assertSuccessful();
        unlink($fichier);

        return $ligne;
    }

    public function test_import_rattache_le_detail_a_la_ligne_parente_sans_modifier_le_budget(): void
    {
        $ligne = $this->importer();

        $detail = LigneBudgetaireDetail::where('code', '61111')->first();
        $this->assertSame($ligne->id, (int) $detail->ligne_budgetaire_id);
        $this->assertEquals(620_000_000, (float) $ligne->fresh()->montant_ouvert, 'La ligne budgétaire est inchangée.');
        $this->assertSame(2, LigneBudgetaireDetail::count());

        $fichier = tempnam(sys_get_temp_dir(), 'det');
        file_put_contents($fichier, "code;intitule;montant\n61111;Voyages et déplacements;250000000\n");
        $this->artisan('budget:importer-detail', ['fichier' => $fichier])->assertSuccessful();
        unlink($fichier);
        $this->assertSame(2, LigneBudgetaireDetail::count(), 'Import idempotent.');
    }

    public function test_code_sans_ligne_parente_refuse(): void
    {
        $fichier = tempnam(sys_get_temp_dir(), 'det');
        file_put_contents($fichier, "code;intitule;montant\n99999;Inconnu;1000\n");
        $this->artisan('budget:importer-detail', ['fichier' => $fichier])->assertFailed();
        unlink($fichier);
        $this->assertSame(0, LigneBudgetaireDetail::count());
    }

    public function test_eb_sur_une_ligne_a_cinq_chiffres_est_hors_pap_imputee_sur_la_ligne_parente(): void
    {
        $ligne = $this->importer();
        $detail = LigneBudgetaireDetail::where('code', '61111')->first();
        $user = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('administrateur');

        $this->actingAs($user)->post(route('besoins.store'), [
            'exercice_id' => $this->exercice->id, 'objet' => 'Billets de mission', 'activite_id' => 'D'.$detail->id,
            'montant_estime' => 900_000, 'justification' => 'Mission statutaire',
        ])->assertRedirect();

        $eb = ExpressionBesoin::where('objet', 'Billets de mission')->firstOrFail();
        $this->assertSame('hors_pap', $eb->categorie);
        $this->assertNull($eb->activite_id);
        $this->assertSame($detail->id, (int) $eb->ligne_detail_id);
        $this->assertSame($ligne->id, (int) $eb->ligne_budgetaire_id);

        $this->actingAs($user)->get(route('besoins.create'))->assertOk()
            ->assertSee('61111 — Voyages et déplacements · 249 100 000 XAF', false);
    }
}
