<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Événements du journal de sécurité émis hors des modèles (accès refusés, échecs d'authentification). */
class JournalSecurite
{
    /** Accès refusé : une trace par acteur, chemin et minute (évite l'inondation du journal). */
    public static function accesRefuse(Request $request, ?string $message = null): void
    {
        try {
            $cle = 'jnl:403:'.($request->user()?->id ?? $request->ip()).':'.sha1($request->path()).':'.now()->format('YmdHi');
            if (! Cache::add($cle, 1, 120)) {
                return;
            }
            AuditLogger::evenement('acces_refuse', null, [
                'user' => $request->user(),
                'motif' => $message ? mb_substr($message, 0, 500) : null,
                'apres' => ['methode' => $request->method(), 'chemin' => '/'.ltrim($request->path(), '/')],
            ]);
        } catch (Throwable) {
            // La journalisation d'un refus ne doit jamais masquer la réponse 403 elle-même.
        }
    }

    /** Échec d'authentification (identifiant inconnu, mot de passe erroné, compte inactif, IP bloquée). */
    public static function echecConnexion(string $email, string $motif): void
    {
        try {
            AuditLogger::evenement('auth_echec', null, [
                'user' => null,
                'user_nom' => mb_substr($email, 0, 255),
                'motif' => $motif,
            ]);
        } catch (Throwable) {
            // login_logs reste la trace de référence des tentatives.
        }
    }
}
