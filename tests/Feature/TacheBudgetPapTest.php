<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Tache;
use App\Models\User;
use App\Services\Rbm\TacheBudgetService;
use Database\Seeders\DepensePermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\CreatesDepenseChain;
use Tests\TestCase;

/**
 * Tâches d'une ligne PAP : une ligne peut porter plusieurs activités (une par expression de besoin) ;
 * le total des tâches d'une activité égale son montant ; le cumul des tâches ne dépasse pas le total de la ligne.
 */
class TacheBudgetPapTest extends TestCase
{
    use CreatesDepenseChain, RefreshDatabase;

    private Exercice $exercice;

    private Activite $activite;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mfa.enabled' => false]);
        $this->seed([RoleSeeder::class, DepensePermissionsSeeder::class]);
        $this->exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        [$this->activite] = $this->activiteEtLigne($this->exercice, 'LB-TB-'.uniqid(), 1_000_000);
        $this->user = User::factory()->create(['actif' => true, 'statut' => 'actif'])->assignRole('administrateur');
    }

    /** @param list<array{0: string, 1: int}> $taches */
    private function eb(string $objet, float $montant, array $taches = [], array $extra = []): TestResponse
    {
        return $this->actingAs($this->user)->from(route('besoins.create'))->post(route('besoins.store'), array_merge([
            'exercice_id' => $this->exercice->id, 'objet' => $objet, 'activite_id' => $this->activite->id, 'montant_estime' => $montant,
            'activite_responsable_id' => $this->user->id,
            'taches_nouvelles' => array_map(fn ($t) => ['intitule' => $t[0], 'montant' => $t[1], 'responsable_user_id' => $this->user->id], $taches),
        ], $extra));
    }

    public function test_total_des_taches_egal_au_montant_de_l_activite_et_non_de_la_ligne(): void
    {
        $this->eb('Atelier régional', 70_000, [['Préparation', 50_000], ['Logistique', 20_000]], ['tache_nouvelle_concernee' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();

        $eb = ExpressionBesoin::where('objet', 'Atelier régional')->firstOrFail();
        $this->assertCount(2, $eb->tachesActivite);
        $this->assertEquals(70_000, $eb->tachesActivite->sum('montant'));
        $this->assertSame($eb->tachesActivite[1]->id, (int) $eb->tache_id, 'La tâche cochée est rattachée au besoin.');
        $this->assertSame($this->user->id, (int) $eb->tachesActivite[0]->responsable_user_id);
        $this->assertSame($this->user->id, (int) $this->activite->fresh()->responsable_user_id);
    }

    public function test_total_des_taches_different_du_montant_de_l_activite_refuse(): void
    {
        $this->eb('Atelier', 70_000, [['Préparation', 50_000]])->assertSessionHasErrors('taches_nouvelles');

        $this->assertSame(0, Tache::count());
        $this->assertSame(0, ExpressionBesoin::count());
    }

    public function test_une_ligne_porte_plusieurs_activites_dans_la_limite_de_son_total(): void
    {
        $this->eb('Activité A', 600_000, [['A1', 600_000]])->assertSessionHasNoErrors();
        $this->eb('Activité B', 400_000, [['B1', 250_000], ['B2', 150_000]])->assertSessionHasNoErrors();
        $this->assertEquals(1_000_000, Tache::where('activite_id', $this->activite->id)->sum('montant'));
        $this->assertSame(['1', '2', '3'], Tache::orderBy('ordre')->get()->map(fn ($t) => substr($t->code, strrpos($t->code, '.') + 1))->all(), 'Codes numérotés à la suite.');

        $this->eb('Activité C', 1_000, [['C1', 1_000]])->assertSessionHasErrors('taches_nouvelles');
        $this->assertSame(2, ExpressionBesoin::count());
    }

    public function test_besoin_sans_tache_reste_possible(): void
    {
        $this->eb('Sans tâche', 50_000)->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_responsable_obligatoire_pour_la_ligne_et_les_taches(): void
    {
        $this->actingAs($this->user)->from(route('besoins.create'))->post(route('besoins.store'), [
            'exercice_id' => $this->exercice->id, 'objet' => 'Sans responsable', 'activite_id' => $this->activite->id, 'montant_estime' => 1000,
        ])->assertSessionHasErrors('activite_responsable_id');

        $this->eb('Tâche sans responsable', 1000, [], ['taches_nouvelles' => [['intitule' => 'T', 'montant' => 1000]]])
            ->assertSessionHasErrors('taches_nouvelles.0.responsable_user_id');
    }

    public function test_modification_remplace_les_taches_de_l_activite(): void
    {
        $this->eb('Activité', 300_000, [['T1', 300_000]])->assertSessionHasNoErrors();
        $eb = ExpressionBesoin::firstOrFail();

        $this->actingAs($this->user)->from(route('besoins.edit', $eb))->put(route('besoins.update', $eb), [
            'exercice_id' => $this->exercice->id, 'objet' => 'Activité', 'activite_id' => $this->activite->id, 'montant_estime' => 900_000,
            'taches_nouvelles' => [['intitule' => 'T1 révisée', 'montant' => 900_000, 'responsable_user_id' => $this->user->id]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['T1 révisée'], $eb->fresh()->tachesActivite->pluck('intitule')->all());
        $this->assertEquals(900_000, Tache::where('activite_id', $this->activite->id)->sum('montant'), 'Les anciennes tâches du besoin ne comptent plus.');
    }

    public function test_fiche_affiche_chaine_rbm_responsable_et_taches(): void
    {
        $this->eb('Fiche', 80_000, [['Tâche fiche', 80_000]])->assertSessionHasNoErrors();
        $eb = ExpressionBesoin::firstOrFail();

        $this->actingAs($this->user)->get(route('besoins.show', $eb))->assertOk()
            ->assertSee('Chaîne RBM')->assertSee('Axe test')->assertSee($this->user->name)->assertSee('Tâche fiche');
    }

    public function test_ecran_pap_refuse_une_tache_qui_depasse_le_total_de_la_ligne(): void
    {
        $this->actingAs($this->user)->post(route('rbm.store', 'tache'), [
            'parent' => $this->activite->id, 'code' => 'T1', 'intitule' => 'Première tâche', 'montant' => 800_000,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('rbm.store', 'tache'), [
            'parent' => $this->activite->id, 'code' => 'T2', 'intitule' => 'Tâche en trop', 'montant' => 300_000,
        ])->assertSessionHas('erreur');

        $this->assertEquals(800_000, Tache::where('activite_id', $this->activite->id)->sum('montant'));
    }

    public function test_total_de_ligne_non_inferieur_au_cumul_des_taches(): void
    {
        Tache::create(['activite_id' => $this->activite->id, 'code' => 'T1', 'intitule' => 'Tâche', 'montant' => 700_000, 'statut' => 'a_faire']);

        $this->expectException(RuntimeException::class);
        app(TacheBudgetService::class)->assertTotalLigne($this->activite, 600_000);
    }
}
