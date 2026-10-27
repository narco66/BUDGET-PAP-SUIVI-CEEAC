<?php

namespace App\Jobs;

use App\Models\ImportLot;
use App\Models\User;
use App\Services\Import\ImportExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Exécution en file d'un lot volumineux (réservé « en cours » au lancement ; une seule tentative). */
class ExecuterImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly int $lotId, public readonly int $userId) {}

    public function handle(ImportExecutionService $execution): void
    {
        $lot = ImportLot::query()->find($this->lotId);
        $user = User::query()->find($this->userId);
        if ($lot && $user && $lot->statut === 'en_cours') {
            try {
                $execution->executer($lot, $user);
            } catch (\RuntimeException $e) {
                report($e);   // dérive détectée : le lot a été recalculé et journalisé
            }
        }
    }
}
