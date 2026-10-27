<?php

use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Liens des notifications rendus relatifs : ceux enregistrés en absolu pointaient vers l'hôte
 * de l'émission (http://localhost en console, port de développement…) et menaient à « Not Found ».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')->orderBy('id')->each(function ($n) {
            $data = json_decode((string) $n->data, true) ?: [];
            $relatif = NotificationDispatcher::lienRelatif($data['url'] ?? null);
            if ($relatif !== ($data['url'] ?? null)) {
                $data['url'] = $relatif;
                DB::table('notifications')->where('id', $n->id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        });

        DB::table('notification_logs')->where('url', 'like', 'http%')->orderBy('id')->each(function ($log) {
            DB::table('notification_logs')->where('id', $log->id)->update(['url' => NotificationDispatcher::lienRelatif($log->url)]);
        });
    }

    public function down(): void
    {
        // Les liens relatifs restent valides : pas de retour arrière.
    }
};
