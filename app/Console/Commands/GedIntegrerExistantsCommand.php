<?php

namespace App\Console\Commands;

use App\Models\PieceJustificative;
use App\Models\Rapport;
use App\Models\User;
use App\Services\Ged\GedIntegrationAutoService;
use App\Services\Ged\GedRapportBridgeService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rattrapage de l'intégration automatique à la GED : pièces justificatives et rapports générés antérieurs
 * (ou dont l'indexation a échoué). Idempotent ; ne modifie ni ne déplace aucun fichier.
 */
class GedIntegrerExistantsCommand extends Command
{
    protected $signature = 'ged:integrer-existants {--dry-run : dénombre sans intégrer}';

    protected $description = 'Intègre à la GED les pièces jointes et rapports générés qui n\'y figurent pas encore';

    public function handle(GedIntegrationAutoService $integration, GedRapportBridgeService $rapports): int
    {
        $pieces = PieceJustificative::query()->whereNull('ged_document_id')->get();
        $aIndexer = Rapport::query()->where('ged_indexe', false)->whereNotNull('chemin')->get();
        $this->info("Pièces à intégrer : {$pieces->count()} ; rapports à intégrer : {$aIndexer->count()}.");
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $ok = 0;
        $echecs = 0;
        foreach ($pieces as $piece) {
            try {
                $integration->indexerPiece($piece);
                $ok++;
            } catch (Throwable $e) {
                $echecs++;
                $this->warn("Pièce {$piece->id} : {$e->getMessage()}");
            }
        }
        foreach ($aIndexer as $rapport) {
            try {
                $auteur = User::find($rapport->genere_par) ?? User::query()->orderBy('id')->firstOrFail();
                $rapports->indexer($rapport, $auteur);
                $rapport->update(['ged_indexe' => true]);
                $ok++;
            } catch (Throwable $e) {
                $echecs++;
                $this->warn("Rapport {$rapport->reference} : {$e->getMessage()}");
            }
        }
        $this->info("Intégrés : {$ok} ; échecs : {$echecs}.");

        return $echecs ? self::FAILURE : self::SUCCESS;
    }
}
