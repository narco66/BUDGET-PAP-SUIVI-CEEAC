<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Strategie\Concerns\FiltreStrategieIndex;
use App\Http\Requests\Strategie\ProgrammeStrategiqueRequest;
use App\Models\Pilier;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\User;
use App\Services\Strategie\StrategieAlignementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgrammeStrategiqueController extends Controller
{
    use FiltreStrategieIndex;

    public function __construct(private readonly StrategieAlignementService $alignementService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProgrammeStrategique::class);

        $programmes = $this->appliquerFiltresIndex(
            ProgrammeStrategique::with('orientationStrategique', 'responsable')->orderBy('code'),
            $request,
            ['code', 'intitule']
        )->paginate(20)->withQueryString();

        return view('strategie.programmes.index', compact('programmes'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProgrammeStrategique::class);

        return view('strategie.programmes.create', $this->options() + [
            'programme' => new ProgrammeStrategique(['orientation_strategique_id' => $request->integer('orientation')]),
        ]);
    }

    public function store(ProgrammeStrategiqueRequest $request): RedirectResponse
    {
        $this->authorize('create', ProgrammeStrategique::class);

        $programme = ProgrammeStrategique::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->synchroniserPontRbm($programme);

        return redirect()->route('strategie.programmes.show', $programme)->with('succes', 'Programme stratégique créé.');
    }

    public function show(ProgrammeStrategique $programmeStrategique): View
    {
        $this->authorize('view', $programmeStrategique);
        $programmeStrategique->load(['orientationStrategique.planStrategique', 'responsable', 'projets', 'pilier']);

        return view('strategie.programmes.show', ['programme' => $programmeStrategique]);
    }

    public function edit(ProgrammeStrategique $programmeStrategique): View
    {
        $this->authorize('update', $programmeStrategique);

        return view('strategie.programmes.edit', $this->options() + ['programme' => $programmeStrategique]);
    }

    public function update(ProgrammeStrategiqueRequest $request, ProgrammeStrategique $programmeStrategique): RedirectResponse
    {
        $this->authorize('update', $programmeStrategique);
        $programmeStrategique->update($request->validated() + ['updated_by' => auth()->id()]);
        $this->synchroniserPontRbm($programmeStrategique->fresh());

        return redirect()->route('strategie.programmes.show', $programmeStrategique)->with('succes', 'Programme mis à jour.');
    }

    public function destroy(ProgrammeStrategique $programmeStrategique): RedirectResponse
    {
        $this->authorize('delete', $programmeStrategique);
        $programmeStrategique->delete();

        return redirect()->route('strategie.programmes.index')->with('succes', 'Programme supprimé.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'orientations' => OrientationStrategique::with('planStrategique')->orderBy('code')->get(),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name', 'matricule']),
            'piliers' => Pilier::orderBy('code')->get(['id', 'code', 'intitule']),
        ];
    }

    private function synchroniserPontRbm(ProgrammeStrategique $programme): void
    {
        if ($programme->pilier_id && $programme->orientation_strategique_id) {
            $this->alignementService->rattacherPilierOrientation(
                Pilier::findOrFail($programme->pilier_id),
                $programme->orientation_strategique_id,
                $programme->id
            );
        }
    }
}
