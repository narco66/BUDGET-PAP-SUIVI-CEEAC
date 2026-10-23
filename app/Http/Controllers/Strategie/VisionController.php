<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\VisionRequest;
use App\Models\Strategie\Vision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisionController extends Controller
{
    use FiltreStrategieIndex;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Vision::class);

        $visions = $this->appliquerFiltresIndex(
            Vision::query()->orderByDesc('date_debut'),
            $request
        )->paginate(20)->withQueryString();

        return view('strategie.visions.index', compact('visions'));
    }

    public function create(): View
    {
        $this->authorize('create', Vision::class);

        return view('strategie.visions.create', ['vision' => new Vision]);
    }

    public function store(VisionRequest $request): RedirectResponse
    {
        $this->authorize('create', Vision::class);

        Vision::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('strategie.visions.index')->with('succes', 'Vision créée.');
    }

    public function show(Vision $vision): View
    {
        $this->authorize('view', $vision);
        $vision->load(['plansStrategiques', 'orientations', 'pieces.auteur']);

        return view('strategie.visions.show', compact('vision'));
    }

    public function edit(Vision $vision): View
    {
        $this->authorize('update', $vision);

        return view('strategie.visions.edit', compact('vision'));
    }

    public function update(VisionRequest $request, Vision $vision): RedirectResponse
    {
        $this->authorize('update', $vision);
        $vision->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('strategie.visions.show', $vision)->with('succes', 'Vision mise à jour.');
    }

    public function destroy(Vision $vision): RedirectResponse
    {
        $this->authorize('delete', $vision);
        $vision->delete();

        return redirect()->route('strategie.visions.index')->with('succes', 'Vision supprimée.');
    }
}
