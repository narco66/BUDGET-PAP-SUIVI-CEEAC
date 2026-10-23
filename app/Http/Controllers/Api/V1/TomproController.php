<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Interop\TomproService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Connecteur d'interopérabilité comptable TOMPRO (D12 — enrichi P2-8).
 * Export décaissements, encaissements et écritures SYSCOHADA.
 */
class TomproController extends Controller
{
    public function __construct(private readonly TomproService $tompro) {}

    public function paiements(Request $request): JsonResponse
    {
        return $this->reponseExport(
            $request,
            $this->tompro->exportPaiements($this->filtres($request)),
            'decaissements',
        );
    }

    public function encaissements(Request $request): JsonResponse
    {
        return $this->reponseExport(
            $request,
            $this->tompro->exportEncaissements($this->filtres($request)),
            'encaissements',
        );
    }

    public function referentiel(): JsonResponse
    {
        return response()->json([
            'source' => 'GEST-BUDGET-PAP',
            'cible' => 'TOMPRO',
            'genere_le' => now()->toIso8601String(),
            ...$this->tompro->referentiel(),
        ]);
    }

    public function synthese(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exercice_id' => ['nullable', 'integer', 'exists:exercices,id'],
        ]);

        return response()->json([
            'source' => 'GEST-BUDGET-PAP',
            'cible' => 'TOMPRO',
            'genere_le' => now()->toIso8601String(),
            'synthese' => $this->tompro->synthese($data['exercice_id'] ?? null),
        ]);
    }

    /** @return array{depuis?: string, limit?: int, exercice_id?: int, non_exportes?: bool} */
    private function filtres(Request $request): array
    {
        return $request->validate([
            'depuis' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'exercice_id' => ['nullable', 'integer', 'exists:exercices,id'],
            'non_exportes' => ['nullable', 'boolean'],
            'marquer_export' => ['nullable', 'boolean'],
        ]);
    }

    private function reponseExport(Request $request, $payload, string $cle): JsonResponse
    {
        if ($request->boolean('marquer_export') && $request->filled('exercice_id')) {
            $this->tompro->marquerExport($request->integer('exercice_id'));
        }

        $response = [
            'source' => 'GEST-BUDGET-PAP',
            'cible' => 'TOMPRO',
            'genere_le' => now()->toIso8601String(),
            'nombre' => $payload->count(),
            $cle => $payload,
        ];

        if ($cle === 'decaissements') {
            $response['ecritures'] = $payload;
        }

        return response()->json($response);
    }
}
