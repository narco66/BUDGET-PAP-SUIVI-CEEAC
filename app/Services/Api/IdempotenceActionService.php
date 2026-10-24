<?php

namespace App\Services\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IdempotenceActionService
{
    /**
     * Rejoue une action métier déjà aboutie si le client renvoie la même clé.
     *
     * @param  callable(): array{status: int, body: array<string, mixed>}  $travail
     * @return array{status: int, body: array<string, mixed>}
     */
    public function executer(Request $request, string $action, callable $travail): array
    {
        $cle = $request->header('Idempotency-Key');
        if (! is_string($cle) || $cle === '') {
            return $travail();
        }

        $userId = $request->user()?->id ?? 0;
        $cacheKey = 'idem:'.$action.':'.$userId.':'.hash('sha256', $cle);

        return Cache::lock($cacheKey.':lock', 10)->block(8, function () use ($cacheKey, $travail) {
            $existant = Cache::get($cacheKey);
            if (is_array($existant) && isset($existant['status'], $existant['body'])) {
                return $existant;
            }

            $resultat = $travail();
            if (($resultat['status'] ?? 500) >= 200 && ($resultat['status'] ?? 500) < 300) {
                Cache::put($cacheKey, $resultat, now()->addDay());
            }

            return $resultat;
        });
    }
}
