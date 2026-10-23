<?php

namespace App\Http\Controllers;

use App\Models\ActionCorrective;
use App\Models\Exercice;
use App\Models\MissionSuivi;
use App\Models\PlanAmelioration;
use App\Models\Recommandation;
use App\Models\Structure;
use App\Models\User;
use App\Services\Budget\ExerciceService;
use App\Services\Suivi\SuiviEvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Plans d'amélioration S&E (phase P2). */
class PlanAmeliorationController extends Controller
{
    public function __construct(private readonly SuiviEvaluationService $service)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);

        $exercice = Exercice::find($request->integer('exercice')) ?? ExerciceService::courant();
        $plans = PlanAmelioration::with(['structure', 'responsable', 'mission'])
            ->when($exercice, fn ($q) => $q->where('exercice_id', $exercice->id))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('suivi-evaluation.plans.index', [
            'plans' => $plans,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'exercice' => $exercice,
            'statuts' => PlanAmelioration::STATUTS,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->service->peutCreer($request->user()), 403);

        return view('suivi-evaluation.plans.form', $this->options() + [
            'plan' => new PlanAmelioration(['statut' => 'brouillon']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->service->peutCreer($request->user()), 403);
        $data = $this->validerPlan($request);
        $data['numero'] ??= $this->numero($data['exercice_id']);
        $plan = PlanAmelioration::create($data);

        return redirect()->route('suivi-evaluation.plans.show', $plan)
            ->with('succes', 'Plan d\'amélioration enregistré.');
    }

    public function show(Request $request, PlanAmelioration $plan): View
    {
        abort_unless($this->service->peutConsulter($request->user()), 403);
        $plan->load(['exercice', 'structure', 'responsable', 'mission', 'evaluationPeriodique', 'actions.recommandation']);

        return view('suivi-evaluation.plans.show', $this->options() + compact('plan'));
    }

    public function storeAction(Request $request, PlanAmelioration $plan): RedirectResponse
    {
        abort_unless($this->service->peutCreer($request->user()), 403);

        $data = $request->validate([
            'libelle' => 'required|string|max:255',
            'description' => 'nullable|string',
            'recommandation_id' => 'nullable|exists:recommandations,id',
            'echeance' => 'nullable|date',
            'statut' => 'required|in:'.implode(',', array_keys(ActionCorrective::STATUTS)),
            'taux' => 'required|numeric|min:0|max:100',
        ]);

        $plan->actions()->create($data);
        $plan->recalculerTaux();

        return back()->with('succes', 'Action corrective ajoutée.');
    }

    private function validerPlan(Request $request): array
    {
        return $request->validate([
            'numero' => 'nullable|string|max:40|unique:plans_amelioration,numero',
            'exercice_id' => 'required|exists:exercices,id',
            'evaluation_periodique_id' => 'nullable|exists:evaluations_periodiques,id',
            'mission_suivi_id' => 'nullable|exists:missions_suivi,id',
            'structure_id' => 'nullable|exists:structures,id',
            'responsable_user_id' => 'nullable|exists:users,id',
            'intitule' => 'required|string|max:255',
            'constat' => 'nullable|string',
            'date_debut' => 'nullable|date',
            'echeance' => 'nullable|date',
            'statut' => 'required|in:'.implode(',', array_keys(PlanAmelioration::STATUTS)),
        ]);
    }

    private function numero(int $exerciceId): string
    {
        $annee = Exercice::find($exerciceId)?->annee ?? now()->year;
        $seq = PlanAmelioration::where('exercice_id', $exerciceId)->count() + 1;

        return sprintf('PA-%d-%03d', $annee, $seq);
    }

    private function options(): array
    {
        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
            'missions' => MissionSuivi::orderByDesc('id')->limit(50)->get(['id', 'numero', 'libelle']),
            'recommandations' => Recommandation::orderByDesc('id')->limit(100)->get(['id', 'numero', 'libelle']),
            'statuts' => PlanAmelioration::STATUTS,
            'actionStatuts' => ActionCorrective::STATUTS,
        ];
    }
}
