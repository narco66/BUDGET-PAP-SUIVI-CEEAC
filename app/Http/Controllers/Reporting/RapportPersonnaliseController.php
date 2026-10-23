<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\RapportPersonnaliseRequest;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\RapportPersonnalise;
use App\Services\Reporting\RapportPersonnaliseService;
use App\Services\Reporting\RapportRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Constructeur de rapports personnalisés (P3-1). */
class RapportPersonnaliseController extends Controller
{
    public function __construct(
        private readonly RapportPersonnaliseService $svc,
        private readonly RapportRenderer $renderer,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Rapport::class);

        $userId = $request->user()->id;
        $items = RapportPersonnalise::with('auteur')
            ->where('actif', true)
            ->where(function ($q) use ($userId) {
                $q->where('partage', true)->orWhere('cree_par', $userId);
            })
            ->latest('id')
            ->paginate(20);

        return view('reporting.modeles.index', [
            'modeles' => $items,
            'sources' => RapportPersonnaliseService::SOURCES,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('reporting.create'), 403);

        return view('reporting.modeles.form', [
            'modele' => new RapportPersonnalise(['partage' => true, 'actif' => true, 'colonnes' => []]),
            'sources' => RapportPersonnaliseService::SOURCES,
        ]);
    }

    public function store(RapportPersonnaliseRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('reporting.create'), 403);

        RapportPersonnalise::create([
            ...$request->validated(),
            'code' => $this->code(),
            'cree_par' => $request->user()->id,
            'partage' => $request->boolean('partage'),
            'actif' => $request->boolean('actif', true),
        ]);

        return redirect()->route('reporting.modeles.index')->with('succes', 'Modèle de rapport enregistré.');
    }

    public function show(Request $request, RapportPersonnalise $rapportPersonnalise): View
    {
        $this->authorizeModele($request, $rapportPersonnalise);
        $exercice = $this->exercice($request);

        return view('reporting.modeles.show', [
            'modele' => $rapportPersonnalise,
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'sources' => RapportPersonnaliseService::SOURCES,
            'apercu' => $this->svc->composer($rapportPersonnalise, $exercice->id, [
                'exercice_annee' => $exercice->annee,
                'structure_id' => $request->integer('structure_id') ?: null,
            ]),
        ]);
    }

    public function edit(Request $request, RapportPersonnalise $rapportPersonnalise): View
    {
        abort_unless($request->user()->can('reporting.create'), 403);
        abort_unless($this->peutModifier($request, $rapportPersonnalise), 403);

        return view('reporting.modeles.form', [
            'modele' => $rapportPersonnalise,
            'sources' => RapportPersonnaliseService::SOURCES,
        ]);
    }

    public function update(RapportPersonnaliseRequest $request, RapportPersonnalise $rapportPersonnalise): RedirectResponse
    {
        abort_unless($request->user()->can('reporting.create'), 403);
        abort_unless($this->peutModifier($request, $rapportPersonnalise), 403);

        $rapportPersonnalise->update([
            ...$request->validated(),
            'partage' => $request->boolean('partage'),
            'actif' => $request->boolean('actif', true),
        ]);

        return redirect()->route('reporting.modeles.show', $rapportPersonnalise)->with('succes', 'Modèle mis à jour.');
    }

    public function destroy(Request $request, RapportPersonnalise $rapportPersonnalise): RedirectResponse
    {
        abort_unless($request->user()->can('reporting.create'), 403);
        abort_unless($this->peutModifier($request, $rapportPersonnalise), 403);
        $rapportPersonnalise->delete();

        return redirect()->route('reporting.modeles.index')->with('succes', 'Modèle supprimé.');
    }

    public function generer(Request $request, RapportPersonnalise $rapportPersonnalise): Response
    {
        $this->authorize('generate', Rapport::class);
        $this->authorizeModele($request, $rapportPersonnalise);

        $format = $request->string('format', 'pdf')->toString();
        $exercice = $this->exercice($request);
        $params = [
            'personnalise_id' => $rapportPersonnalise->id,
            'exercice_annee' => $exercice->annee,
            'structure_id' => $request->integer('structure_id') ?: null,
        ];

        $resultat = $this->renderer->generer('personnalise', $exercice, $format, $params, $request->user());

        return response($resultat['contenu'], 200, [
            'Content-Type' => $resultat['mime'],
            'Content-Disposition' => 'attachment; filename="'.$resultat['filename'].'"',
        ]);
    }

    private function code(): string
    {
        $seq = RapportPersonnalise::withTrashed()->count() + 1;

        return sprintf('RPT-PERS-%03d', $seq);
    }

    private function exercice(Request $request): Exercice
    {
        return $request->filled('exercice')
            ? Exercice::findOrFail($request->integer('exercice'))
            : (Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->firstOrFail());
    }

    private function authorizeModele(Request $request, RapportPersonnalise $modele): void
    {
        $this->authorize('viewAny', Rapport::class);
        abort_unless(
            $modele->actif && ($modele->partage || $modele->cree_par === $request->user()->id),
            403
        );
    }

    private function peutModifier(Request $request, RapportPersonnalise $modele): bool
    {
        return $modele->cree_par === $request->user()->id || $request->user()->can('reporting.admin');
    }
}
