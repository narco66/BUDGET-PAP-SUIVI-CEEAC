<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Encaissement;
use App\Models\Recette;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints recettes et encaissements (I-P3). */
class RecettesApiController extends Controller
{
    public function recettes(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('recette.voir'), 403);

        $items = Recette::with(['exercice', 'categorie', 'etatMembre'])
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('id')
            ->paginate(50);

        return JsonResource::collection($items->through(fn (Recette $r) => [
            'id' => $r->id,
            'reference' => $r->reference,
            'observations' => $r->observations,
            'statut' => $r->statut,
            'montant_prevu' => (float) $r->montant_prevu,
            'montant_realise' => (float) $r->montant_realise,
            'exercice_id' => $r->exercice_id,
            'categorie' => $r->categorie?->libelle,
        ]));
    }

    public function encaissements(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('recette.voir'), 403);

        $items = Encaissement::with('recette')
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('recette'), fn ($q) => $q->where('recette_id', $request->recette))
            ->orderByDesc('date_encaissement')
            ->paginate(50);

        return JsonResource::collection($items->through(fn (Encaissement $e) => [
            'id' => $e->id,
            'reference' => $e->reference,
            'statut' => $e->statut,
            'montant' => (float) $e->montant,
            'montant_xaf' => (float) $e->montant_xaf,
            'date_encaissement' => $e->date_encaissement?->toDateString(),
            'recette' => $e->recette?->reference,
        ]));
    }

    public function showRecette(Request $request, Recette $recette): JsonResource
    {
        abort_unless($request->user()->can('recette.voir'), 403);

        $recette->load(['exercice', 'categorie', 'encaissements']);

        return new JsonResource([
            'id' => $recette->id,
            'reference' => $recette->reference,
            'observations' => $recette->observations,
            'statut' => $recette->statut,
            'montant_prevu' => (float) $recette->montant_prevu,
            'montant_realise' => (float) $recette->montant_realise,
            'encaissements_count' => $recette->encaissements->count(),
        ]);
    }
}
