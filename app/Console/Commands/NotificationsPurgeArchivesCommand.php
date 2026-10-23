<?php



namespace App\Console\Commands;



use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;



/** Purge les notifications archivées au-delà du délai configuré (N-P2). */

class NotificationsPurgeArchivesCommand extends Command

{

    protected $signature = 'notifications:purge-archives {--jours= : Délai en jours (défaut : config)}';



    protected $description = 'Supprime les notifications in-app archivées anciennes';



    public function handle(): int

    {

        $jours = (int) ($this->option('jours') ?: config('notifications.purge_archives_jours', 365));

        $seuil = now()->subDays($jours);



        $count = DB::table('notifications')

            ->whereNotNull('archive_at')

            ->where('archive_at', '<', $seuil)

            ->delete();



        $this->info("{$count} notification(s) archivée(s) purgée(s) (> {$jours} jours).");



        return self::SUCCESS;

    }

}

