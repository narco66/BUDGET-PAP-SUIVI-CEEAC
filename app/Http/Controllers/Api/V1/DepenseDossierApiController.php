<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Services\Depense\ChaineClotureService;
use App\Services\Depense\DepenseDossierService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dossier unifié EB → PAY — mêmes agrégats que Blade, chemins SPA. */
class DepenseDossierApiController extends Controller
{
    public function __construct(
        private readonly DepenseDossierService $dossiers,
        private readonly ChaineClotureService $cloture,
    ) {}

    public function besoin(Request $request, ExpressionBesoin $besoin): JsonResponse
    {
        $this->authorize('view', $besoin);
        $payload = $this->dossiers->depuisBesoin($besoin);
        $payload['cloture'] = $payload['engagement']
            ? $this->cloture->etat($payload['engagement'])
            : null;

        return response()->json(['data' => $this->api($payload, $request)]);
    }

    public function engagement(Request $request, Engagement $engagement): JsonResponse
    {
        $this->authorize('view', $engagement);
        $payload = $this->dossiers->depuisEngagement($engagement);
        $payload['cloture'] = $this->cloture->etat($engagement);

        return response()->json(['data' => $this->api($payload, $request)]);
    }

    /** @param  array<string, mixed>  $payload */
    private function api(array $payload, Request $request): array
    {
        $besoin = $payload['besoin'];
        $engagement = $payload['engagement'];
        $tache = $payload['tache'] ?? null;

        $timeline = collect($payload['timeline'] ?? [])->map(function (array $etape) {
            return [
                'type' => $etape['type'],
                'label' => $etape['label'],
                'numero' => $etape['numero'],
                'statut' => $etape['statut'],
                'montant' => Montant::api($etape['montant'] ?? 0),
                'date' => $etape['date'] instanceof \DateTimeInterface
                    ? $etape['date']->format('Y-m-d')
                    : $etape['date'],
                'path' => $this->cheminSpa($etape),
            ];
        })->values();

        $pieces = collect($payload['pieces'] ?? [])->map(function (array $ligne) {
            $piece = $ligne['piece'] ?? null;

            return [
                'etape' => $ligne['etape'] ?? null,
                'numero' => $ligne['numero'] ?? null,
                'nom' => $piece->nom ?? ($ligne['reference'] ?? null),
                'id' => $piece->id ?? null,
                'typologie' => $ligne['typologie'] ?? null,
            ];
        })->values();

        return [
            'besoin' => $besoin ? [
                'id' => $besoin->id,
                'numero' => $besoin->numero,
                'statut' => $besoin->statut,
                'path' => '/besoins/'.$besoin->id,
            ] : null,
            'engagement' => $engagement ? [
                'id' => $engagement->id,
                'numero' => $engagement->numero,
                'statut' => $engagement->statut,
                'path' => '/engagements/'.$engagement->id,
            ] : null,
            'tache' => $tache ? [
                'code' => $tache->code,
                'intitule' => $tache->intitule,
            ] : null,
            'priorite' => $payload['priorite'] ?? null,
            'cloture' => $payload['cloture'] ?? null,
            'timeline' => $timeline,
            'pieces' => $pieces,
        ];
    }

    /** @param  array<string, mixed>  $etape */
    private function cheminSpa(array $etape): ?string
    {
        $route = (string) ($etape['route'] ?? '');
        if ($route === '') {
            return null;
        }
        if (preg_match('#/(besoins|engagements)/(\d+)#', $route, $m)) {
            return '/'.$m[1].'/'.$m[2];
        }
        if (preg_match('#/depense/(liquidation|ordonnancement|paiement)/(\d+)#', $route, $m)) {
            return '/'.$m[1].'s/'.$m[2];
        }

        return null;
    }
}
