<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesDemoGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_construisent_un_graphe_rbm_budget_depense_coherent(): void
    {
        $activite = Activite::factory()->create();
        $tache = Tache::factory(['activite_id' => $activite->id])->create();
        $ligne = LigneBudgetaire::factory([
            'activite_id' => $activite->id,
            'structure_id' => $activite->structure_id,
        ])->create();
        $besoin = ExpressionBesoin::factory([
            'exercice_id' => $ligne->exercice_id,
            'structure_id' => $ligne->structure_id,
            'activite_id' => $activite->id,
            'tache_id' => $tache->id,
            'ligne_budgetaire_id' => $ligne->id,
            'montant_estime' => 5000000,
        ])->valide()->create();
        $engagement = Engagement::factory([
            'exercice_id' => $ligne->exercice_id,
            'expression_besoin_id' => $besoin->id,
            'activite_id' => $activite->id,
            'tache_id' => $tache->id,
            'montant_total' => 5000000,
        ])->create();

        $activite->refresh();
        $sousProduit = SousProduit::findOrFail($activite->sous_produit_id);
        $produit = Produit::findOrFail($sousProduit->produit_id);

        $this->assertSame($produit->id, $activite->produit_id);
        $this->assertSame($activite->id, $tache->activite_id);
        $this->assertSame($ligne->id, $besoin->ligne_budgetaire_id);
        $this->assertSame($besoin->id, $engagement->expression_besoin_id);
        $this->assertLessThanOrEqual((float) $ligne->montant_ouvert, (float) $ligne->montant_engage);
        $this->assertLessThanOrEqual((float) $ligne->montant_engage, (float) $ligne->montant_liquide);
        $this->assertLessThanOrEqual((float) $ligne->montant_liquide, (float) $ligne->montant_ordonnance);
        $this->assertLessThanOrEqual((float) $ligne->montant_ordonnance, (float) $ligne->montant_paye);
    }
}
