<?php

namespace App\Jobs;

use App\Models\WebhookLivraison;
use App\Support\QueueNames;
use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Livraison asynchrone d'un webhook avec journalisation (I-P2). */
class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $livraisonId)
    {
        $this->onQueue(QueueNames::webhooks());
    }

    public function handle(WebhookService $webhooks): void
    {
        $webhooks->livrer($this->livraisonId);
    }
}
