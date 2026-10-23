<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Models\MissionSuivi;
use App\Models\Structure;
use App\Models\User;
use App\Services\Budget\ExerciceService;
use App\Services\Suivi\SuiviEvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Missions de suivi-évaluation (phase P2). */
class MissionSuiviController extends Controller
{
    public function __construct(private readonly SuiviEvaluationService $service)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);

        $exercice = Exercice::find($request->integer('exercice')) ?? ExerciceService::courant();
        $missions = MissionSuivi::with(['structure', 'responsable'])
            ->when($exercice, fn ($q) => $q->where('exercice_id', $exercice->id))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('suivi-evaluation.missions.index', [
            'missions' => $missions,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'exercice' => $exercice,
            'statuts' => MissionSuivi::STATUTS,
            'types' => MissionSuivi::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->service->peutCreer($request->user()), 403);

        return view('suivi-evaluation.missions.form', $this->options() + [
            'mission' => new MissionSuivi(['statut' => 'planifiee', 'type' => 'suivi']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->service->peutCreer($request->user()), 403);
        $data = $this->valider($request);
        $data['numero'] ??= $this->numero($data['exercice_id']);
        MissionSuivi::create($data);

        return redirect()->route('suivi-evaluation.missions.index', ['exercice' => $data['exercice_id']])
            ->with('succes', 'Mission enregistrée.');
    }

    public function show(Request $request, MissionSuivi $mission): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);
        $mission->load(['exercice', 'structure', 'responsable', 'evaluationPeriodique', 'plans.actions']);

        return view('suivi-evaluation.missions.show', compact('mission'));
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'numero' => 'nullable|string|max:40|unique:missions_suivi,numero',
            'exercice_id' => 'required|exists:exercices,id',
            'structure_id' => 'nullable|exists:structures,id',
            'responsable_user_id' => 'nullable|exists:users,id',
            'evaluation_periodique_id' => 'nullable|exists:evaluations_periodiques,id',
            'type' => 'required|in:'.implode(',', array_keys(MissionSuivi::TYPES)),
            'libelle' => 'required|string|max:255',
            'objectif' => 'nullable|string',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'statut' => 'required|in:'.implode(',', array_keys(MissionSuivi::STATUTS)),
            'score_mission' => 'nullable|numeric|min:0|max:100',
            'observations' => 'nullable|string',
        ]);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = MissionSuivi::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('MS-%d-%03d', $annee, $seq);
    }

    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
            'types' => MissionSuivi::TYPES,
            'statuts' => MissionSuivi::STATUTS,
        ];
    }
}
