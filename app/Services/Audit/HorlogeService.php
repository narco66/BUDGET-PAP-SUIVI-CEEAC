<?php

namespace App\Services\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Contrôle de la référence temporelle (§ 5.2) : écart entre l'horloge de l'application et celle de la
 * base, état de synchronisation NTP de l'hôte lorsqu'il est lisible. Ne corrige jamais une heure : signale.
 */
class HorlogeService
{
    /** @return array{statut: string, ecart_base_s: float|null, ntp: string, source: string|null, derniere_synchro: string|null, controle_le: string} */
    public function controler(): array
    {
        $ecart = $this->ecartBase();
        $ntp = $this->etatNtp();
        $seuil = (float) config('audit.horloge.ecart_max_secondes', 2);

        $statut = match (true) {
            $ecart !== null && abs($ecart) > $seuil => 'alerte',
            $ntp['etat'] === 'non_synchronise' => 'alerte',
            $ecart === null => 'non_verifiable',
            default => 'ok',
        };

        return [
            'statut' => $statut,
            'ecart_base_s' => $ecart,
            'seuil_s' => $seuil,
            'ntp' => $ntp['etat'],
            'source' => $ntp['source'],
            'derniere_synchro' => $ntp['derniere_synchro'],
            'controle_le' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /** Écart (secondes) horloge base − horloge application ; null si non mesurable. */
    public function ecartBase(): ?float
    {
        try {
            $avant = microtime(true);
            $valeur = DB::getDriverName() === 'pgsql'
                ? DB::selectOne('select clock_timestamp() as t')->t
                : DB::selectOne('select CURRENT_TIMESTAMP as t')->t;
            $apres = microtime(true);
            $base = CarbonImmutable::parse((string) $valeur, 'UTC')->getPreciseTimestamp(6) / 1_000_000;
            $ecart = $base - ($avant + $apres) / 2;
            // SQLite ne donne que la seconde : l'arrondi peut produire jusqu'à 1 s d'écart apparent.
            if (DB::getDriverName() !== 'pgsql' && abs($ecart) < 1.0) {
                $ecart = 0.0;
            }

            return round($ecart, 3);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{etat: string, source: string|null, derniere_synchro: string|null} */
    public function etatNtp(): array
    {
        $inconnu = ['etat' => 'non_verifiable', 'source' => null, 'derniere_synchro' => null];
        if (! config('audit.horloge.verifier_ntp', true) || app()->runningUnitTests()) {
            return $inconnu;
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $p = new Process(['w32tm', '/query', '/status']);
                $p->setTimeout(5)->run();
                // La console Windows produit du CP850 (accents, espace insécable 0xFF) lorsqu'elle n'est pas en UTF-8.
                $brut = $p->getOutput();
                $sortie = mb_check_encoding($brut, 'UTF-8') ? $brut : mb_convert_encoding($brut, 'UTF-8', 'CP850');
                if (! $p->isSuccessful() && $sortie === '') {
                    return $inconnu;
                }
                // Sortie localisée : espace insécable possible avant les deux-points (français).
                preg_match('/^Source[\s\x{00A0}\x{202F}]*:[\s\x{00A0}]*(.+)$/mu', $sortie, $source);
                preg_match('/(?:derni.re synchronisation r.ussie|Last Successful Sync Time)[\s\x{00A0}\x{202F}]*:[\s\x{00A0}]*(.+)$/imu', $sortie, $synchro);
                $local = preg_match('/Local CMOS Clock|Free-running/i', $sortie) === 1;

                return [
                    'etat' => ($synchro[1] ?? null) && ! $local ? 'synchronise' : 'non_synchronise',
                    'source' => isset($source[1]) ? trim($source[1]) : null,
                    'derniere_synchro' => isset($synchro[1]) ? trim($synchro[1]) : null,
                ];
            }

            $p = new Process(['timedatectl', 'show', '-p', 'NTPSynchronized', '--value']);
            $p->setTimeout(5)->run();
            if (! $p->isSuccessful()) {
                return $inconnu;
            }

            return ['etat' => trim($p->getOutput()) === 'yes' ? 'synchronise' : 'non_synchronise', 'source' => 'systemd-timesyncd', 'derniere_synchro' => null];
        } catch (Throwable) {
            return $inconnu;
        }
    }
}
