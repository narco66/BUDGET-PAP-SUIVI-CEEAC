<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\User;
use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Garantit que les données de démonstration sont cohérentes, complètes,
 * conformes aux invariants des finances publiques, et que le seeding est
 * NON DESTRUCTIF et idempotent.
 */
class DonneesDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_donnees_demo_coherentes_et_completes(): void
    {
        $this->seed(SafeDatabaseSeeder::class);

        // -- Référentiel RBAC : chaque utilisateur a un rôle, chaque rôle des permissions
        $this->assertGreaterThanOrEqual(13, User::count());
        User::with('roles')->get()->each(
            fn (User $u) => $this->assertGreaterThanOrEqual(1, $u->roles->count(), "Utilisateur {$u->email} sans rôle")
        );
        Role::all()->each(
            fn (Role $r) => $this->assertGreaterThanOrEqual(1, $r->permissions->count(), "Rôle {$r->name} sans permission")
        );

        // -- Intégrité référentielle : lignes rattachées à la nomenclature, activités à la chaîne GAR/RBM
        $this->assertSame(0, LigneBudgetaire::whereNull('rubrique_id')->count(), 'Ligne sans rubrique');
        $this->assertGreaterThan(0, Activite::count());
        $this->assertSame(0, Activite::whereNull('sous_produit_id')->count(), 'Activité non rattachée au RBM');

        // -- Chaîne de la dépense complète et cohérente
        $engagements = Engagement::with('imputations')->get();
        $this->assertGreaterThanOrEqual(9, $engagements->count());

        foreach ($engagements as $eng) {
            // chaque engagement a au moins une imputation
            $this->assertGreaterThanOrEqual(1, $eng->imputations->count(), "Engagement {$eng->numero} sans imputation");
            // Σ imputations = montant total de l'engagement
            $this->assertEquals(
                (float) $eng->montant_total,
                (float) $eng->imputations->sum('montant'),
                "Σ imputations ≠ montant_total pour {$eng->numero}"
            );
        }

        // -- Invariants des compteurs de crédit par ligne :
        //    payé ≤ ordonnancé ≤ liquidé ≤ engagé ≤ ouvert
        foreach (LigneBudgetaire::all() as $l) {
            $this->assertLessThanOrEqual((float) $l->montant_ouvert, (float) $l->montant_engage, "engagé>ouvert sur {$l->code}");
            $this->assertLessThanOrEqual((float) $l->montant_engage, (float) $l->montant_liquide, "liquidé>engagé sur {$l->code}");
            $this->assertLessThanOrEqual((float) $l->montant_liquide, (float) $l->montant_ordonnance, "ordonnancé>liquidé sur {$l->code}");
            $this->assertLessThanOrEqual((float) $l->montant_ordonnance, (float) $l->montant_paye, "payé>ordonnancé sur {$l->code}");
        }

        // -- Données exploitables dans les tableaux de bord (au moins une ligne consommée)
        $this->assertGreaterThan(0, (float) LigneBudgetaire::sum('montant_engage'), 'Aucun crédit engagé : tableaux de bord vides');
        $this->assertGreaterThan(0, (float) LigneBudgetaire::sum('montant_paye'), 'Aucun paiement : tableaux de bord vides');

        // -- Couverture des cas métier
        $this->assertTrue(ExpressionBesoin::where('statut', 'rejete')->exists(), 'Cas « besoin rejeté » manquant');
        $this->assertTrue(Engagement::where('statut', 'annule')->exists(), 'Cas « engagement annulé » manquant');
        $this->assertTrue(Engagement::has('imputations', '>=', 2)->exists(), 'Cas « multi-imputations » manquant');
        $this->assertTrue(Paiement::where('statut', 'soumis')->exists(), 'Cas « paiement en attente » manquant');
        $this->assertTrue(Paiement::where('statut', 'valide')->exists(), 'Cas « paiement validé » manquant');

        // -- Paramètres système présents
        $this->assertNotNull(Parametre::valeur('institution.sigle'));
        $this->assertSame('XAF', Parametre::valeur('budget.devise'));
    }

    public function test_seeding_non_destructif_et_idempotent(): void
    {
        // Donnée pré-existante (ne doit jamais être supprimée ni modifiée)
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $temoin = User::create([
            'name' => 'Témoin Existant', 'email' => 'temoin@ceeac.test',
            'password' => bcrypt('secret-temoin'), 'actif' => true, 'statut' => 'actif',
        ]);

        $this->seed(SafeDatabaseSeeder::class);

        // 1) Donnée pré-existante intacte
        $temoin->refresh();
        $this->assertTrue($temoin->exists);
        $this->assertSame('Témoin Existant', $temoin->name);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret-temoin', $temoin->password));

        // Empreinte après un premier seeding
        $apres1 = $this->empreinte();

        // 2) Second seeding : aucun doublon, aucune suppression
        $this->seed(SafeDatabaseSeeder::class);
        $apres2 = $this->empreinte();

        $this->assertSame($apres1, $apres2, 'Le seeding n’est pas idempotent (comptages différents au 2ᵉ passage)');

        // Pas de doublon de numéro d'engagement
        $numeros = Engagement::pluck('numero');
        $this->assertSame($numeros->count(), $numeros->unique()->count(), 'Doublon de numéro d’engagement détecté');

        // Témoin toujours présent après les re-seedings
        $this->assertTrue(User::where('email', 'temoin@ceeac.test')->exists());
    }

    /** Comptage de toutes les tables surveillées (sert à comparer deux passages). */
    private function empreinte(): array
    {
        return [
            'users' => User::count(),
            'engagements' => Engagement::count(),
            'besoins' => ExpressionBesoin::count(),
            'paiements' => Paiement::count(),
            'parametres' => Parametre::count(),
            'lignes_engage' => (float) LigneBudgetaire::sum('montant_engage'),
            'lignes_paye' => (float) LigneBudgetaire::sum('montant_paye'),
        ];
    }
}
