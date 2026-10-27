<?php

namespace App\Services\Reporting;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\RapportModele;
use App\Models\User;
use App\Services\Ged\GedRapportBridgeService;
use Illuminate\Support\Facades\Log;

/** Workflow MOA et indexation GED des rapports (P2). */
class RapportWorkflowService
{
    public function __construct(
        private readonly GedRapportBridgeService $gedBridge,
    ) {
    }

    public function indexerGedSiBesoin(Rapport $rapport, User $user): void
    {
        if ($rapport->ged_indexe || ! config('reporting.ged_auto_index', true)) {
            return;
        }

        try {
            // Intégration automatique : tout rapport généré rejoint la GED, quel que soit le profil de l'auteur
            // (l'accès au document reste régi par les règles de consultation de la GED).
            $this->gedBridge->indexer($rapport, $user);
            $rapport->update(['ged_indexe' => true]);
        } catch (\Throwable $e) {
            Log::warning('Indexation GED rapport échouée', [
                'rapport' => $rapport->reference,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function valider(Rapport $rapport, User $user): Rapport
    {
        if ($rapport->statut_workflow !== 'genere') {
            throw new \RuntimeException('Seul un rapport généré peut être validé.');
        }

        $rapport->statut_workflow = 'valide';
        $rapport->valide_par = $user->id;
        $rapport->valide_le = now();
        $rapport->save();

        return $rapport->refresh();
    }

    public function publier(Rapport $rapport, User $user): Rapport
    {
        if ($rapport->statut_workflow !== 'valide') {
            throw new \RuntimeException('Le rapport doit être validé MOA avant publication.');
        }

        $rapport->statut_workflow = 'publie';
        $rapport->publie_par = $user->id;
        $rapport->publie_le = now();
        $rapport->verrouille = true;
        $rapport->save();

        $this->indexerGedSiBesoin($rapport, $user);

        app(\App\Services\WebhookService::class)->declencher('reporting.publie', [
            'reference' => $rapport->reference,
            'type' => $rapport->type,
            'exercice_id' => $rapport->exercice_id,
        ]);

        return $rapport->refresh();
    }

    public function resoudreModele(string $type): ?RapportModele
    {
        return RapportModele::where('type', $type)->where('actif', true)->first();
    }
}
