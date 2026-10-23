<?php

namespace App\Services\AdministrationTechnique;

use App\Models\BlockedIp;
use App\Models\LoginLog;

/**
 * Protection anti brute-force du formulaire de connexion (Lot T4).
 * Verrouillage automatique d'une IP après {SEUIL} échecs dans une fenêtre de {FENETRE_MIN} minutes,
 * pour une durée de {BLOCAGE_MIN} minutes. Les tentatives sont comptées depuis `login_logs`.
 */
class ProtectionConnexionService
{
    public const SEUIL = 5;
    public const FENETRE_MIN = 15;
    public const BLOCAGE_MIN = 15;

    /** Retourne le blocage actif d'une IP, ou null. */
    public function ipBloquee(string $ip): ?BlockedIp
    {
        return BlockedIp::where('ip', $ip)->actif()->latest('id')->first();
    }

    /**
     * À appeler APRÈS journalisation d'un échec : si le seuil est atteint sur la fenêtre, verrouille l'IP.
     */
    public function enregistrerEchec(string $ip): ?BlockedIp
    {
        $echecs = LoginLog::where('ip', $ip)->where('succes', false)
            ->where('created_at', '>=', now()->subMinutes(self::FENETRE_MIN))->count();

        if ($echecs < self::SEUIL) {
            return null;
        }

        return BlockedIp::updateOrCreate(
            ['ip' => $ip, 'permanent' => false],
            ['tentatives' => $echecs, 'bloque_jusqua' => now()->addMinutes(self::BLOCAGE_MIN), 'motif' => "Brute-force : {$echecs} échecs"]
        );
    }

    /** Lève le verrou automatique d'une IP (connexion réussie). Conserve les blocages permanents/manuels. */
    public function reinitialiser(string $ip): void
    {
        BlockedIp::where('ip', $ip)->where('permanent', false)->delete();
    }

    /** Blocage manuel par un administrateur (permanent ou temporisé). */
    public function bloquerManuel(string $ip, ?string $motif, ?int $userId, bool $permanent = true, ?int $minutes = null): BlockedIp
    {
        return BlockedIp::updateOrCreate(
            ['ip' => $ip, 'permanent' => $permanent],
            [
                'motif' => $motif ?? 'Blocage manuel',
                'bloque_par' => $userId,
                'bloque_jusqua' => $permanent ? null : now()->addMinutes($minutes ?? self::BLOCAGE_MIN),
            ]
        );
    }
}
