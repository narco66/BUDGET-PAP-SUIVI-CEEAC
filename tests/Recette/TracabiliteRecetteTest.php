<?php

namespace Tests\Recette;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Services\Depense\TracabiliteDepenseService;
use Illuminate\Support\Facades\DB;

/**
 * Recette de la traçabilité sur la base PostgreSQL réelle (transaction annulée) : chaque dossier de la chaîne
 * est reconstitué sans erreur ni écriture, et l'écran s'affiche pour un dossier réel (EB-2026-0040).
 */
class TracabiliteRecetteTest extends RecettePgTestCase
{
    private function empreinte(): array
    {
        return collect(['workflow_taches', 'workflow_instances', 'workflow_instance_etapes', 'notifications', 'audit_workflow',
            'expressions_besoin', 'engagements', 'liquidations', 'ordonnancements', 'paiements'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count().'|'.DB::table($t)->max($t === 'audit_workflow' ? 'created_at' : 'updated_at')])->all();
    }

    public function test_toute_la_chaine_reelle_est_tracable_sans_ecriture(): void
    {
        $avant = $this->empreinte();
        $svc = app(TracabiliteDepenseService::class);
        $stats = ['dossiers' => 0, 'en_attente' => 0, 'manquantes' => 0];
        $anomalies = collect();

        foreach (ExpressionBesoin::query()->orderBy('id')->get() as $eb) {
            $d = $svc->dossier($eb);
            $stats['dossiers'] += $d['noeuds']->count();
            $stats['en_attente'] += $d['en_attente']->count();
            $stats['manquantes'] += $d['en_attente']->filter(fn ($n) => $n['situation']['attendu']['manquante'])->count();
            $d['anomalies']->each(fn ($a) => $anomalies->push($a['code']));
            foreach ($d['noeuds'] as $n) {
                $this->assertTrue($n['niveau'] === 0 || $d['besoin'] !== null, "{$n['numero']} rattaché à son EB d'origine.");
                if ($n['situation']['attendu'] && ! $n['situation']['attendu']['manquante']) {
                    $this->assertNotEmpty($n['situation']['attendu']['titulaires'], "{$n['numero']} : acteur attendu identifié.");
                }
            }
        }
        foreach (Engagement::query()->whereNull('expression_besoin_id')->get() as $eng) {
            $this->assertContains('origine_absente', $svc->dossier($eng)['anomalies']->pluck('code')->all());
        }

        fwrite(STDERR, "\nTraçabilité : ".json_encode($stats).' anomalies '.json_encode($anomalies->countBy()->all())."\n");
        $this->assertSame($avant, $this->empreinte(), 'Aucune écriture (lecture seule).');
    }

    public function test_ecran_eb_2026_0040_et_recherche(): void
    {
        $eb = ExpressionBesoin::query()->where('numero', 'EB-2026-0040')->first();
        if (! $eb) {
            $this->markTestSkipped('EB-2026-0040 absente.');
        }
        config(['mfa.enabled' => false]);
        $admin = User::query()->where('email', 'administrateur@ceeac.test')->firstOrFail();
        $ord = $eb->engagements()->first()?->liquidations()->first()?->ordonnancements()->first();

        $this->actingAs($admin)->get(route('depense.tracabilite.index', ['q' => 'EB-2026-0040']))
            ->assertRedirect(route('depense.tracabilite.show', ['besoin', $eb->id]));
        $page = $this->actingAs($admin)->get(route('depense.tracabilite.show', ['besoin', $eb->id]))->assertOk()->assertSee('Situation actuelle');
        if ($ord) {
            $page->assertSee($ord->numero);
            $this->actingAs($admin)->get(route('depense.tracabilite.show', ['ordonnancement', $ord->id]))->assertOk()->assertSee('EB-2026-0040');
        }
    }
}
