<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Requests\Strategie\AlignementStrategiqueRequest;
use App\Models\Activite;
use App\Models\Strategie\AlignementStrategique;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\Strategie\Vision;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AlignementStrategiqueController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AlignementStrategique::class);

        $alignements = AlignementStrategique::with(['vision', 'orientationStrategique', 'planStrategique', 'alignable'])
            ->latest()
            ->paginate(25);

        return view('strategie.alignements.index', compact('alignements'));
    }

    public function create(): View
    {
        $this->authorize('create', AlignementStrategique::class);

        return view('strategie.alignements.create', $this->options());
    }

    public function store(AlignementStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', AlignementStrategique::class);

        AlignementStrategique::create($request->validated() + ['created_by' => auth()->id()]);

        return redirect()->route('strategie.alignements.index')->with('succes', 'Alignement enregistré.');
    }

    public function destroy(AlignementStrategique $alignement): RedirectResponse
    {
        $this->authorize('delete', $alignement);
        $alignement->delete();

        return redirect()->route('strategie.alignements.index')->with('succes', 'Alignement supprimé.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'visions' => Vision::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'plans' => PlanStrategique::orderBy('reference')->get(['id', 'reference', 'intitule']),
            'orientations' => OrientationStrategique::with('planStrategique')->orderBy('code')->get(),
            'programmes' => ProgrammeStrategique::orderBy('code')->get(['id', 'code', 'intitule']),
            'projets' => ProjetStrategique::orderBy('code')->get(['id', 'code', 'intitule']),
            'activites' => Activite::orderBy('code')->limit(200)->get(['id', 'code', 'intitule']),
        ];
    }
}
