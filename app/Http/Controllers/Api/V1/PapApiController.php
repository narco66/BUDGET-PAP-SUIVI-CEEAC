<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints PAP (I-P3). */
class PapApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('pap.voir'), 403);

        $items = Pap::with('exercice', 'structure', 'responsable')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('id')
            ->paginate(30);

        return JsonResource::collection($items->through(fn (Pap $p) => [
            'id' => $p->id,
            'reference' => $p->reference,
            'libelle' => $p->libelle,
            'statut' => $p->statut,
            'version' => $p->version,
            'exercice' => $p->exercice?->only(['id', 'annee']),
            'publie_le' => $p->publie_le?->toIso8601String(),
        ]));
    }

    public function show(Request $request, Pap $pap): JsonResource
    {
        abort_unless($request->user()->can('pap.voir'), 403);

        $pap->load(['exercice', 'structure', 'responsable', 'piliers']);

        return new JsonResource([
            'id' => $pap->id,
            'reference' => $pap->reference,
            'libelle' => $pap->libelle,
            'statut' => $pap->statut,
            'version' => $pap->version,
            'observations' => $pap->observations,
            'exercice' => $pap->exercice?->only(['id', 'annee', 'libelle']),
            'structure' => $pap->structure?->only(['id', 'code', 'libelle']),
            'responsable' => $pap->responsable?->only(['id', 'name', 'email']),
            'piliers_count' => $pap->piliers->count(),
            'publie_le' => $pap->publie_le?->toIso8601String(),
        ]);
    }
}
