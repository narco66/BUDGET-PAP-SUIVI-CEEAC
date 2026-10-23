<?php

namespace Tests\Feature;

use App\Support\CockpitCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2 — Cache versionné des agrégats de cockpit : mémorise les calculs lourds, mais toute écriture
 * de compteur incrémente la génération et invalide le cache → jamais de chiffre périmé (cohérence).
 */
class CockpitCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_memorise_le_resultat(): void
    {
        $appels = 0;
        $calcul = function () use (&$appels) {
            $appels++;

            return ['valeur' => 42];
        };

        $a = CockpitCache::remember('exec', 1, $calcul);
        $b = CockpitCache::remember('exec', 1, $calcul);

        $this->assertSame(['valeur' => 42], $a);
        $this->assertSame($a, $b);
        $this->assertSame(1, $appels, 'Le second appel doit servir la valeur mémorisée.');
    }

    public function test_bump_invalide_le_cache(): void
    {
        $appels = 0;
        $calcul = function () use (&$appels) {
            $appels++;

            return $appels;
        };

        $this->assertSame(1, CockpitCache::remember('exec', 1, $calcul));
        $this->assertSame(1, CockpitCache::remember('exec', 1, $calcul)); // mémorisé

        CockpitCache::bump(); // une écriture de compteur survient

        $this->assertSame(2, CockpitCache::remember('exec', 1, $calcul), 'Après bump, l’agrégat doit être recalculé.');
    }

    public function test_les_exercices_sont_caches_independamment(): void
    {
        $this->assertSame('ex1', CockpitCache::remember('exec', 1, fn () => 'ex1'));
        $this->assertSame('ex2', CockpitCache::remember('exec', 2, fn () => 'ex2'));
    }

    public function test_le_chokepoint_depense_invalide_le_cache(): void
    {
        $avant = CockpitCache::version();

        // Tout mouvement de la chaîne de dépense passe par HistoriqueDepenseService, qui bumpe AVANT
        // de persister le journal. La persistance peut échouer sur un engagement non sauvegardé :
        // seul le bump nous intéresse ici.
        try {
            app(\App\Services\Depense\HistoriqueDepenseService::class)
                ->enregistrer(new \App\Models\Engagement(['numero' => 'ENG-TEST', 'statut' => 'soumis']), 'test_invalidation');
        } catch (\Throwable) {
            // ignoré : le bump a déjà eu lieu.
        }

        $this->assertGreaterThan($avant, CockpitCache::version(), 'Un mouvement de dépense doit invalider le cache.');
    }
}
