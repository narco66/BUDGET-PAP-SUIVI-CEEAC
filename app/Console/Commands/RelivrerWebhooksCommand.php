<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookLivraison;
use Illuminate\Console\Command;

/** Relance les livraisons webhook en attente (I-P2). */
class RelivrerWebhooksCommand extends Command
{
    protected $signature = 'webhooks:relivrer';

    protected $description = 'Relance les livraisons webhook dont la prochaine tentative est due';

    public function handle(): int
    {
        $livraisons = WebhookLivraison::query()
            ->where('statut', 'en_attente')
            ->where(function ($q) {
                $q->whereNull('prochaine_tentative_le')
                    ->orWhere('prochaine_tentative_le', '<=', now());
            })
            ->pluck('id');

        foreach ($livraisons as $id) {
            DeliverWebhookJob::dispatch($id);
        }

        $this->info("{$livraisons->count()} livraison(s) relancée(s).");

        return self::SUCCESS;
    }
}
