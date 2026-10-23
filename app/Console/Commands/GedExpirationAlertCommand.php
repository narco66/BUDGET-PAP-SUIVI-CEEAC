<?php

namespace App\Console\Commands;

use App\Enums\Ged\DocumentStatut;
use App\Models\Ged\GedDocument;
use App\Services\Ged\GedNotificationService;
use Illuminate\Console\Command;

/** Alerte les documents arrivant à échéance (conservation / expiration). */
class GedExpirationAlertCommand extends Command
{
    protected $signature = 'ged:expiration-alerte {--jours= : Jours avant expiration}';

    protected $description = 'Envoie les alertes GED pour documents expirant prochainement';

    public function handle(GedNotificationService $notifications): int
    {
        $jours = (int) ($this->option('jours') ?: config('ged.notifications.expiration_jours_avant', 30));
        $limite = now()->addDays($jours)->toDateString();

        $documents = GedDocument::query()
            ->whereNotNull('date_expiration')
            ->whereDate('date_expiration', '<=', $limite)
            ->whereDate('date_expiration', '>=', now()->toDateString())
            ->where('est_archive', false)
            ->get();

        $count = 0;
        foreach ($documents as $document) {
            $reste = now()->diffInDays($document->date_expiration, false);
            $notifications->expirationProche($document, max(0, (int) $reste));
            $count++;
        }

        GedDocument::query()
            ->whereNotNull('date_expiration')
            ->whereDate('date_expiration', '<', now()->toDateString())
            ->where('statut', '!=', DocumentStatut::Expire)
            ->update(['statut' => DocumentStatut::Expire]);

        $this->info("Alertes envoyées : {$count} document(s).");

        return self::SUCCESS;
    }
}
