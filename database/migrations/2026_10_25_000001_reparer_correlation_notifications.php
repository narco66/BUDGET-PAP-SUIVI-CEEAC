<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Répare les effets de l'ancienne corrélation NotificationDispatcher (`latest()` sur des UUID) :
 *  - type_code / module / criticite des notifications réécrits depuis leur propre contenu (data, qui fait foi) ;
 *  - notification_logs.laravel_notification_id ré-associé à la notification réellement reçue
 *    (même destinataire, même type, même titre, envoi à ±5 s).
 */
return new class extends Migration
{
    public function up(): void
    {
        $parUtilisateur = [];
        DB::table('notifications')->orderBy('created_at')->get(['id', 'notifiable_id', 'data', 'type_code', 'module', 'criticite', 'created_at'])
            ->each(function ($n) use (&$parUtilisateur) {
                $data = json_decode((string) $n->data, true) ?: [];
                $attendu = array_filter([
                    'type_code' => $data['type_code'] ?? $data['type'] ?? null,
                    'module' => $data['module'] ?? null,
                    'criticite' => $data['criticite'] ?? null,
                ]);
                $ecart = ($attendu['type_code'] ?? $n->type_code) !== $n->type_code
                    || ($attendu['module'] ?? $n->module) !== $n->module
                    || ($attendu['criticite'] ?? $n->criticite) !== $n->criticite;
                if ($attendu && $ecart) {
                    DB::table('notifications')->where('id', $n->id)->update($attendu);
                }
                $parUtilisateur[$n->notifiable_id][] = [
                    'id' => $n->id,
                    'type' => $attendu['type_code'] ?? null,
                    'titre' => $data['titre'] ?? null,
                    'ts' => strtotime((string) $n->created_at),
                ];
            });

        $attribues = [];
        DB::table('notification_logs')->where('canal', 'like', '%database%')->orderBy('id')
            ->get(['id', 'destinataire_id', 'type_code', 'titre', 'envoye_le', 'created_at'])
            ->each(function ($log) use (&$parUtilisateur, &$attribues) {
                $ts = strtotime((string) ($log->envoye_le ?? $log->created_at));
                $trouve = null;
                foreach ($parUtilisateur[$log->destinataire_id] ?? [] as $c) {
                    if (! isset($attribues[$c['id']]) && $c['type'] === $log->type_code && $c['titre'] === $log->titre && abs($c['ts'] - $ts) <= 5) {
                        $trouve = $c['id'];
                        break;
                    }
                }
                if ($trouve) {
                    $attribues[$trouve] = true;
                }
                DB::table('notification_logs')->where('id', $log->id)->update(['laravel_notification_id' => $trouve]);
            });
    }

    public function down(): void
    {
        // Réparation de données : pas de retour à l'état incohérent.
    }
};
