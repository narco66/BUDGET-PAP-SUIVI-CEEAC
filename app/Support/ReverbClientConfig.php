<?php

namespace App\Support;

/** Configuration client Reverb / Echo pour le cockpit live (P3-7 prod). */
final class ReverbClientConfig
{
    /** @return array{enabled: bool, key: ?string, host: string, port: int, scheme: string, authEndpoint: string} */
    public static function pourFront(): array
    {
        $broadcast = config('broadcasting.default', 'null');
        $reverb = config('broadcasting.connections.reverb', []);
        $live = config('cockpit.live.reverb', []);
        $enabled = ($live['enabled'] ?? false)
            && $broadcast === 'reverb'
            && filled($reverb['key'] ?? null);

        $scheme = (string) ($live['scheme'] ?? $reverb['options']['scheme'] ?? 'http');
        $host = (string) ($live['host'] ?? $reverb['options']['host'] ?? 'localhost');
        $port = (int) ($live['port'] ?? $reverb['options']['port'] ?? 8080);

        return [
            'enabled' => $enabled,
            'key' => $enabled ? (string) $reverb['key'] : null,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'authEndpoint' => url('/broadcasting/auth'),
        ];
    }

    /** Sources CSP connect-src pour WebSocket Reverb. */
    public static function cspConnectSources(): array
    {
        if (! self::pourFront()['enabled']) {
            return [];
        }

        $cfg = self::pourFront();
        $host = $cfg['host'];
        $port = $cfg['port'];
        $scheme = $cfg['scheme'];

        return array_filter([
            "{$scheme}://{$host}:{$port}",
            $scheme === 'https' ? "wss://{$host}:{$port}" : "ws://{$host}:{$port}",
        ]);
    }
}
