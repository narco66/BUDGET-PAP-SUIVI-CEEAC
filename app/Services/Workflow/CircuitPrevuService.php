<?php

namespace App\Services\Workflow;

use App\Http\Controllers\PieceController;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Services\Depense\BesoinCircuitService;
use App\Services\Depense\SeuilOrdonnancementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Circuit de validation affiché sur un dossier de la chaîne de la dépense :
 * - le circuit qui s'appliquera tant que le dossier n'est pas soumis (même règle que la soumission) ;
 * - l'historique des circuits des dossiers d'origine (EB → ENG → LIQ → ORD → PAY).
 */
class CircuitPrevuService
{
    /** Code du circuit que la soumission du dossier lancera, ou null s'il n'est pas déterminable. */
    public function code(Model $dossier): ?string
    {
        try {
            return match (true) {
                $dossier instanceof ExpressionBesoin => app(BesoinCircuitService::class)->code($dossier),
                $dossier instanceof Engagement => 'ENGAGEMENT',
                $dossier instanceof Liquidation => 'LIQUIDATION',
                $dossier instanceof Ordonnancement => app(SeuilOrdonnancementService::class)->circuit($dossier),
                $dossier instanceof Paiement => 'PAIEMENT',
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /** Circuit prévu (définition active) pour un dossier non encore soumis. */
    public function prevu(Model $dossier): ?Workflow
    {
        $code = $this->code($dossier);

        return $code ? Workflow::with(['etapes' => fn ($q) => $q->orderBy('ordre')])->where('code', $code)->where('actif', true)->first() : null;
    }

    /**
     * Derniers circuits des dossiers d'origine, du plus proche au plus ancien.
     *
     * @return Collection<int, array{etape: string, dossier: Model, instance: WorkflowInstance}>
     */
    public function amont(Model $dossier): Collection
    {
        return collect(PieceController::dossiersAmont($dossier))
            ->map(fn ($a) => ['etape' => $a[0], 'dossier' => $a[1], 'instance' => $a[1]->workflowActif?->load('etapes.acteur', 'workflow')])
            ->filter(fn ($a) => $a['instance'] !== null)
            ->values();
    }
}
