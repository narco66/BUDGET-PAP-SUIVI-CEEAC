<?php

namespace App\Http\Controllers;

use App\Models\BesoinConsolidation;
use App\Models\Exercice;
use App\Models\Structure;
use App\Services\Depense\BesoinConsolidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consolidation des expressions de besoin (phase D1). */
class BesoinConsolidationController extends Controller
{
    public function __construct(private readonly BesoinConsolidationService $consolidation)
    {
    }

    public function index(Request $request): View
    {
        abort_unless(app(\App\Policies\ExpressionBesoinPolicy::class)->consolider($request->user()), 403);

        $exercice = Exercice::where('annee', (int) $request->input('exercice', date('Y')))->firstOrFail();
        $structureId = $request->filled('structure') ? (int) $request->input('structure') : null;

        return view('besoins.consolidation', [
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee', 'libelle']),
            'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'sigle']),
            'structureId' => $structureId,
            'synthese' => $this->consolidation->synthese($exercice->id, $structureId),
            'historique' => BesoinConsolidation::with('structure', 'auteur')
                ->where('exercice_id', $exercice->id)
                ->when($structureId, fn ($q) => $q->where('structure_id', $structureId), fn ($q) => $q->whereNull('structure_id'))
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app(\App\Policies\ExpressionBesoinPolicy::class)->consolider($request->user()), 403);

        $data = $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
        ]);

        $consolidation = $this->consolidation->consolider(
            (int) $data['exercice_id'],
            $data['structure_id'] ?? null,
            $request->user()->id,
        );

        return back()->with('succes', "Consolidation enregistrée ({$consolidation->reference}).");
    }
}
