<?php

namespace App\Http\Controllers;

use App\Http\Requests\RisqueRequest;
use App\Models\CategorieRisque;
use App\Models\Exercice;
use App\Models\Risque;
use App\Models\Structure;
use App\Models\User;
use App\Services\Risque\RisqueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Registre & cartographie des risques institutionnels (ISO 31000 / COSO — évaluation des risques).
 * Criticité = probabilité × impact. Permissions `risques.*`.
 */
class RisqueController extends Controller
{
    /** Bornes de criticité par niveau (cf. Risque::niveauPour). */
    private const BORNES_NIVEAU = [
        'faible' => [1, 3], 'modere' => [4, 7], 'eleve' => [8, 11], 'critique' => [12, 16],
    ];

    public function __construct(private RisqueService $service)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('risques.voir'), 403);

        $risques = Risque::with(['categorie', 'proprietaire', 'structure'])
            ->when($request->filled('categorie'), fn ($q) => $q->where('categorie_risque_id', $request->integer('categorie')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('niveau') && isset(self::BORNES_NIVEAU[$request->niveau]), function ($q) use ($request) {
                [$lo, $hi] = self::BORNES_NIVEAU[$request->niveau];
                $q->whereRaw('probabilite * impact BETWEEN ? AND ?', [$lo, $hi]);
            })
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$request->q}%")->orWhere('libelle', 'like', "%{$request->q}%")))
            ->orderByRaw('probabilite * impact DESC')->orderBy('code')
            ->paginate(20)->withQueryString();

        return view('risques.index', [
            'risques' => $risques,
            'categories' => CategorieRisque::orderBy('ordre')->get(),
            'statuts' => Risque::STATUTS,
            'stats' => $this->service->statistiques(),
        ]);
    }

    public function cartographie(Request $request): View
    {
        abort_unless($request->user()->can('risques.voir'), 403);

        return view('risques.cartographie', [
            'carto' => $this->service->cartographie(true),
            'existants' => $this->service->existantsNormalises(),
            'stats' => $this->service->statistiques(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('risques.creer'), 403);

        return view('risques.form', $this->optionsFormulaire(new Risque(['statut' => 'identifie', 'probabilite' => 1, 'impact' => 1])));
    }

    public function store(RisqueRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('risques.creer'), 403);
        $risque = Risque::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('risques.show', $risque)->with('succes', "Risque {$risque->code} enregistré.");
    }

    public function show(Request $request, Risque $risque): View
    {
        abort_unless($request->user()->can('risques.voir'), 403);
        $risque->load(['categorie', 'proprietaire', 'structure', 'exercice']);

        return view('risques.show', ['risque' => $risque]);
    }

    public function edit(Request $request, Risque $risque): View
    {
        abort_unless($request->user()->can('risques.modifier'), 403);

        return view('risques.form', $this->optionsFormulaire($risque));
    }

    public function update(RisqueRequest $request, Risque $risque): RedirectResponse
    {
        abort_unless($request->user()->can('risques.modifier'), 403);
        $risque->update($request->validated());

        return redirect()->route('risques.show', $risque)->with('succes', "Risque {$risque->code} mis à jour.");
    }

    public function archiver(Request $request, Risque $risque): RedirectResponse
    {
        abort_unless($request->user()->can('risques.supprimer'), 403);
        $risque->delete();

        return redirect()->route('risques.index')->with('succes', "Risque {$risque->code} archivé.");
    }

    /** Export du registre des risques (xlsx / csv / pdf). */
    public function export(Request $request, string $format)
    {
        abort_unless($request->user()->can('risques.exporter'), 403);
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $fichier = 'registre-risques-'.now()->format('Ymd');

        if ($format === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.risques', [
                'risques' => Risque::with(['categorie'])->orderByRaw('probabilite * impact DESC')->orderBy('code')->get(),
            ])->setPaper('a4', 'landscape')->download("{$fichier}.pdf");
        }

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\RisquesExport, "{$fichier}.{$format}");
    }

    private function optionsFormulaire(Risque $risque): array
    {
        return [
            'risque' => $risque,
            'categories' => CategorieRisque::orderBy('ordre')->get(),
            'structures' => Structure::orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
            'probabilites' => Risque::PROBABILITES,
            'impacts' => Risque::IMPACTS,
            'statuts' => Risque::STATUTS,
        ];
    }
}
