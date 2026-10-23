<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache des agrégats de cockpit (P2 — performance) versionné par une « génération » globale.
 *
 * Chaque écriture de compteur (chaîne de dépense, budget, recettes, indicateurs) incrémente la
 * génération via {@see bump()} : les clés de cache embarquant la génération, toute donnée modifiée
 * devient instantanément inatteignable et l'agrégat est recalculé au prochain accès. Le cache ne
 * peut donc JAMAIS renvoyer un chiffre périmé — la cohérence stricte des montants est préservée.
 * Le TTL court n'est qu'un filet de sécurité pour d'éventuelles écritures non instrumentées.
 */
final class CockpitCache
{
    /** Filet de sécurité : durée de vie maximale d'un agrégat (secondes). */
    public const TTL = 120;

    private const GEN = 'cockpit:gen';

    public static function version(): int
    {
        return (int) Cache::get(self::GEN, 0);
    }

    /** Invalide tous les agrégats de cockpit (à appeler après une écriture de compteur). */
    public static function bump(?int $exerciceId = null, array $domains = ['budget']): void
    {
        try {
            // increment() renvoie false sur clé absente (magasin database) : on initialise alors.
            if (Cache::increment(self::GEN) === false) {
                Cache::forever(self::GEN, self::version() + 1);
            }
        } catch (\Throwable) {
            Cache::forever(self::GEN, self::version() + 1);
        }

        if (config('cockpit.live.enabled', true)) {
            event(new \App\Events\CockpitMetricsUpdated(self::version(), $exerciceId, $domains));
        }
    }

    /**
     * Mémorise le résultat d'un agrégat sous une clé versionnée par exercice.
     *
     * @template TValeur
     * @param  Closure(): TValeur  $rappel
     * @return TValeur
     */
    public static function remember(string $cle, ?int $exerciceId, Closure $rappel): mixed
    {
        $key = "cockpit:{$cle}:".($exerciceId ?? 'all').':v'.self::version();

        if (Cache::has($key)) {
            $value = Cache::get($key);
            if (! self::containsIncompleteClass($value)) {
                return $value;
            }

            Cache::forget($key);
        }

        $value = $rappel();
        Cache::put($key, $value, self::TTL);

        return $value;
    }

    private static function containsIncompleteClass(mixed $value): bool
    {
        if (is_object($value) && get_class($value) === '__PHP_Incomplete_Class') {
            return true;
        }

        if ($value instanceof \Traversable) {
            foreach ($value as $item) {
                if (self::containsIncompleteClass($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::containsIncompleteClass($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
