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
        // Mémorisée le temps de la requête (ou de la tâche de file) : un cockpit lit jusqu'à une dizaine
        // d'agrégats, chacun relisait la génération dans le magasin de cache.
        $memo = self::memo();
        if ($memo !== null && isset($memo['v'])) {
            return $memo['v'];
        }
        $version = (int) Cache::get(self::GEN, 0);
        if ($memo !== null) {
            $memo['v'] = $version;
        }

        return $version;
    }

    /** Mémoire « scoped » (réinitialisée entre requêtes et entre tâches de file) ; null hors conteneur. */
    private static function memo(): ?\ArrayObject
    {
        return function_exists('app') && app()->bound('cockpit.memo') ? app('cockpit.memo') : null;
    }

    /** Invalide tous les agrégats de cockpit (à appeler après une écriture de compteur). */
    public static function bump(?int $exerciceId = null, array $domains = ['budget']): void
    {
        $memo = self::memo();
        if ($memo !== null && isset($memo['v'])) {
            unset($memo['v']);
        }
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

        // Une seule lecture (au lieu de has() puis get()) ; une valeur nulle n'est jamais mise en cache par remember().
        $value = Cache::get($key);
        if ($value !== null) {
            if (! self::containsIncompleteClass($value)) {
                return self::decoder($value);
            }

            Cache::forget($key);
        }

        $value = $rappel();
        // Stockage en types natifs : le magasin n'autorise aucune classe à la désérialisation
        // (cache.serializable_classes = false) ; une Collection relue devenait __PHP_Incomplete_Class
        // et l'agrégat était recalculé à chaque affichage.
        Cache::put($key, self::encoder($value), self::TTL);

        return $value;
    }

    /** Collections et objets simples → tableaux marqués (types natifs uniquement). */
    private static function encoder(mixed $value): mixed
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return ['__cockpit_collection' => array_map(self::encoder(...), $value->all())];
        }
        if ($value instanceof \stdClass) {
            return ['__cockpit_objet' => array_map(self::encoder(...), (array) $value)];
        }
        if (is_array($value)) {
            return array_map(self::encoder(...), $value);
        }

        return $value;
    }

    private static function decoder(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_key_exists('__cockpit_collection', $value) && count($value) === 1) {
            return collect(array_map(self::decoder(...), $value['__cockpit_collection']));
        }
        if (array_key_exists('__cockpit_objet', $value) && count($value) === 1) {
            return (object) array_map(self::decoder(...), $value['__cockpit_objet']);
        }

        return array_map(self::decoder(...), $value);
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
