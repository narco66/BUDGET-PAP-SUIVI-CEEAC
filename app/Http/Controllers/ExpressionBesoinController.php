<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpressionBesoinRequest;
use App\Http\Requests\WorkflowDecisionRequest;
use App\Models\Activite;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Structure;
use App\Models\Tache;
use App\Services\AccessScopeService;
use App\Services\DocumentPdf;
use App\Services\WorkflowService;
use App\Support\Numero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ExpressionBesoinController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly AccessScopeService $scopes,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExpressionBesoin::class);

        $query = $this->scopes
            ->filtrerStructuresMetier(
                ExpressionBesoin::with('structure', 'demandeur', 'exercice', 'tache', 'activite')->latest(),
                $request->user(),
            );

        if ($request->filled('tache_id')) {
            $query->where('tache_id', $request->integer('tache_id'));
        }

        if ($request->filled('priorite')) {
            $query->where('priorite', $request->input('priorite'));
        }

        $besoins = $query->paginate(20)->withQueryString();

        return view('besoins.index', array_merge(compact('besoins'), $this->options()));
    }

    public function create(): View
    {
        $this->authorize('create', ExpressionBesoin::class);

        return view('besoins.create', $this->options());
    }

    public function store(ExpressionBesoinRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpressionBesoin::class);
        try {
            app(\App\Services\Budget\ExerciceService::class)->assertExerciceOuvert((int) $request->exercice_id);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage())->withInput();
        }
        $annee = Exercice::find($request->exercice_id)?->annee ?? (int) date('Y');

        $besoin = ExpressionBesoin::create([
            ...$request->validated(),
            'numero' => Numero::suivant('expressions_besoin', 'EB', $annee),
            'demandeur_id' => $request->user()->id,
            'statut' => 'brouillon',
        ]);

        return redirect()->route('besoins.show', $besoin)->with('succes', 'Expression du besoin creee.');
    }

    public function show(ExpressionBesoin $besoin): View
    {
        $this->authorize('view', $besoin);
        $besoin->load('structure', 'demandeur', 'activite', 'tache', 'exercice', 'workflowActif.etapes.acteur', 'pieces.auteur');
        $instance = $besoin->workflowActif;
        $peutAgir = $instance && $this->workflow->peutAgir($instance, auth()->user());

        return view('besoins.show', compact('besoin', 'instance', 'peutAgir'));
    }

    public function edit(ExpressionBesoin $besoin): View
    {
        $this->authorize('update', $besoin);

        return view('besoins.edit', array_merge(['besoin' => $besoin], $this->options()));
    }

    public function update(ExpressionBesoinRequest $request, ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('update', $besoin);
        $besoin->update($request->validated());

        return redirect()->route('besoins.show', $besoin)->with('succes', 'Expression du besoin mise a jour.');
    }

    public function destroy(ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('delete', $besoin);
        if ($besoin->engagements()->exists()) {
            return back()->with('erreur', 'Annulation de l’EB interdite : un engagement lié existe. Annuler d’abord l’engagement et ses effets.');
        }
        $besoin->delete();

        return redirect()->route('besoins.index')->with('succes', 'Expression du besoin supprimee.');
    }

    public function soumettre(ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('update', $besoin);

        try {
            app(\App\Services\Budget\ExerciceService::class)->assertExerciceOuvert((int) $besoin->exercice_id);
            $circuit = app(\App\Services\Depense\BesoinCircuitService::class)->code($besoin);
            $this->workflow->demarrer($besoin, $circuit, auth()->user());
            app(\App\Services\Notifications\DepenseNotificationService::class)
                ->notifierBesoinSoumis($besoin->fresh());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Besoin soumis pour validation.');
    }

    public function agir(WorkflowDecisionRequest $request, ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('agir', $besoin);
        $data = $request->validated();

        $instance = $besoin->workflowActif;
        if (! $instance) {
            return back()->with('erreur', 'Aucun circuit actif.');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                auth()->user(),
                $data['decision'],
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Decision enregistree.');
    }

    public function convertir(ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('view', $besoin);
        abort_unless($besoin->statut === 'valide', 422, 'Le besoin doit etre valide.');
        abort_unless(auth()->user()->can('engagement.creer'), 403);

        $besoin->update(['statut' => 'converti']);

        return redirect()->route('engagements.create', ['besoin' => $besoin->id])
            ->with('succes', "Besoin converti : completez l'engagement.");
    }

    public function pdf(ExpressionBesoin $besoin, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $besoin);

        return $pdf->besoin($besoin)->stream("besoin_{$besoin->numero}.pdf");
    }

    private function options(): array
    {
        $user = auth()->user();
        $structures = $this->scopes
            ->filtrerReferentielStructures(Structure::actif()->orderBy('libelle'), $user)
            ->get(['id', 'code', 'libelle']);

        $activites = $this->scopes
            ->filtrerStructuresMetier(Activite::orderBy('code'), $user)
            ->limit(1000)
            ->get(['id', 'code', 'intitule']);

        $taches = Tache::orderBy('code')->limit(1000)->get(['id', 'activite_id', 'code', 'intitule']);

        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => $structures,
            'activites' => $activites,
            'taches' => $taches,
            'priorites' => ExpressionBesoin::PRIORITES,
        ];
    }
}
