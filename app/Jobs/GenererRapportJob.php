<?php

namespace App\Jobs;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Notifications\ReportingNotificationService;
use App\Support\QueueNames;
use App\Services\Reporting\RapportRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Génère un rapport institutionnel en arrière-plan (P1-8).
 * Le rapport est archivé par {@see RapportRenderer} ; l'auteur est notifié à la fin.
 */
class GenererRapportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    /** @param array<string, mixed> $params */
    public function __construct(
        public readonly string $type,
        public readonly int $exerciceId,
        public readonly string $format,
        public readonly array $params,
        public readonly int $userId,
    ) {
        $this->onQueue(config('reporting.async.queue', QueueNames::reporting()));
    }

    public function handle(RapportRenderer $renderer, ReportingNotificationService $notifications): void
    {
        $exercice = Exercice::find($this->exerciceId);
        $user = User::find($this->userId);

        if (! $exercice || ! $user) {
            return;
        }

        $resultat = $renderer->generer($this->type, $exercice, $this->format, $this->params, $user);
        $notifications->notifierAuteurRapportPret($resultat['rapport'], $user);
    }

    public function failed(Throwable $e): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        app(ReportingNotificationService::class)->notifierEchecGeneration(
            $user,
            $this->type,
            $this->format,
            $e->getMessage(),
        );
    }
}
