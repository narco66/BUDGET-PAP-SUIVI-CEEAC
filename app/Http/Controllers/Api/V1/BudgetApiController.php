<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Services\Budget\SuiviFinancierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints budget institutionnel (I-P3). */
class BudgetApiController extends Controller
{
    public function __construct(private readonly SuiviFinancierService $suivi) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('budget.voir') || $request->user()->can('ligne_budgetaire.voir'), 403);

        $items = Budget::with('exercice')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('id')
            ->paginate(30);

        return JsonResource::collection($items->through(fn (Budget $b) => [
            'id' => $b->id,
            'reference' => $b->reference,
            'libelle' => $b->libelle,
            'statut' => $b->statut,
            'montant_total' => (float) $b->montant_total,
            'exercice' => $b->exercice?->only(['id', 'annee', 'libelle']),
            'adopte_le' => $b->adopte_le?->toIso8601String(),
        ]));
    }

    public function show(Request $request, Budget $budget): JsonResource
    {
        abort_unless($request->user()->can('budget.voir') || $request->user()->can('ligne_budgetaire.voir'), 403);

        $budget->load('exercice', 'structure');

        return new JsonResource([
            'id' => $budget->id,
            'reference' => $budget->reference,
            'libelle' => $budget->libelle,
            'statut' => $budget->statut,
            'montant_total' => (float) $budget->montant_total,
            'exercice' => $budget->exercice?->only(['id', 'annee', 'libelle', 'statut']),
            'structure' => $budget->structure?->only(['id', 'code', 'libelle']),
            'adopte_le' => $budget->adopte_le?->toIso8601String(),
        ]);
    }

    public function synthese(Request $request, Budget $budget): JsonResource
    {
        abort_unless($this->suivi->peutConsulter($request->user()), 403);

        return new JsonResource([
            'budget_id' => $budget->id,
            'exercice_id' => $budget->exercice_id,
            'synthese' => $this->suivi->hub($budget->exercice_id)['budget'],
        ]);
    }
}
