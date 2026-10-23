<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Services\Reporting\CockpitLiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoints JSON live / drill-down cockpit exécutif (P3-7). */
class CockpitLiveController extends Controller
{
    public function __construct(private readonly CockpitLiveService $live) {}

    public function snapshot(Request $request): JsonResponse
    {
        $this->authorizeLive($request);
        $exercice = $this->exercice($request);

        return response()->json(['data' => $this->live->snapshot($exercice->id)]);
    }

    public function drilldown(Request $request): JsonResponse
    {
        $this->authorizeLive($request);
        $validated = $request->validate([
            'exercice' => ['nullable', 'integer', 'exists:exercices,id'],
            'niveau' => ['required', 'string', 'in:categorie,axe,structure,pilier'],
            'parent' => ['required', 'string', 'max:120'],
        ]);
        $exercice = $this->exercice($request);

        return response()->json([
            'data' => $this->live->drilldown($exercice->id, $validated['niveau'], $validated['parent']),
        ]);
    }

    public function ping(Request $request): JsonResponse
    {
        $this->authorizeLive($request);
        $since = (int) $request->integer('since', 0);

        return response()->json(['data' => $this->live->ping($since)]);
    }

    private function authorizeLive(Request $request): void
    {
        abort_unless(
            $request->user()?->can('tableau_bord.voir') || $request->user()?->can('reporting.view'),
            403
        );
    }

    private function exercice(Request $request): Exercice
    {
        $id = $request->integer('exercice') ?: Exercice::where('statut', 'ouvert')->orderByDesc('annee')->value('id');

        return Exercice::findOrFail($id ?: Exercice::orderByDesc('annee')->value('id'));
    }
}
