<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ged\ConnaissanceRequest;
use App\Models\Ged\Connaissance;
use App\Models\Ged\GedDocument;
use App\Services\Ged\ConnaissanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Wiki & procédures institutionnelles (P3-3). */
class ConnaissanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('ged.voir'), 403);

        $items = Connaissance::with('auteur')
            ->visiblesPour($request->user())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('statut') && $request->user()->can('ged.admin'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($sub) use ($request) {
                $term = '%'.$request->q.'%';
                $sub->where('titre', 'like', $term)->orWhere('resume', 'like', $term)->orWhere('contenu', 'like', $term);
            }))
            ->latest('publie_le')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('ged.connaissances.index', [
            'connaissances' => $items,
            'types' => Connaissance::TYPES,
            'modules' => Connaissance::MODULES,
            'statuts' => Connaissance::STATUTS,
            'synthese' => [
                'total' => Connaissance::visiblesPour($request->user())->count(),
                'procedures' => Connaissance::visiblesPour($request->user())->where('type', 'procedure')->where('statut', 'publie')->count(),
                'wiki' => Connaissance::visiblesPour($request->user())->where('type', 'wiki')->where('statut', 'publie')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('ged.creer'), 403);

        return view('ged.connaissances.form', $this->options() + [
            'connaissance' => new Connaissance([
                'type' => $request->query('type', 'wiki'),
                'module' => $request->query('module', 'general'),
                'statut' => 'brouillon',
            ]),
        ]);
    }

    public function store(ConnaissanceRequest $request, ConnaissanceService $service): RedirectResponse
    {
        abort_unless($request->user()->can('ged.creer'), 403);
        $item = $service->creer($request->donnees(), $request->user());

        return redirect()->route('ged.connaissances.show', $item)->with('succes', 'Contenu enregistré en brouillon.');
    }

    public function show(Request $request, Connaissance $connaissance): View
    {
        abort_unless($request->user()->can('ged.voir'), 403);
        abort_unless(
            $connaissance->statut === 'publie'
            || $connaissance->auteur_id === $request->user()->id
            || $request->user()->can('ged.admin'),
            404
        );

        $connaissance->load(['auteur', 'publiePar', 'documentGed', 'versions.auteur']);

        return view('ged.connaissances.show', compact('connaissance'));
    }

    public function edit(Request $request, Connaissance $connaissance): View
    {
        abort_unless($connaissance->estModifiablePar($request->user()), 403);

        return view('ged.connaissances.form', $this->options() + compact('connaissance'));
    }

    public function update(ConnaissanceRequest $request, Connaissance $connaissance, ConnaissanceService $service): RedirectResponse
    {
        abort_unless($connaissance->estModifiablePar($request->user()), 403);
        $service->mettreAJour($connaissance, $request->donnees(), $request->user());

        return redirect()->route('ged.connaissances.show', $connaissance)->with('succes', 'Contenu mis à jour (v'.$connaissance->fresh()->version.').');
    }

    public function destroy(Request $request, Connaissance $connaissance): RedirectResponse
    {
        abort_unless($request->user()->can('ged.admin'), 403);
        $connaissance->delete();

        return redirect()->route('ged.connaissances.index')->with('succes', 'Contenu supprimé.');
    }

    public function publier(Request $request, Connaissance $connaissance, ConnaissanceService $service): RedirectResponse
    {
        abort_unless($request->user()->can('ged.publier'), 403);
        abort_unless($connaissance->estModifiablePar($request->user()), 403);
        $service->publier($connaissance, $request->user());

        return back()->with('succes', 'Contenu publié.');
    }

    public function archiver(Request $request, Connaissance $connaissance, ConnaissanceService $service): RedirectResponse
    {
        abort_unless($request->user()->canany(['ged.publier', 'ged.admin']), 403);
        $service->archiver($connaissance);

        return back()->with('succes', 'Contenu archivé.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'types' => Connaissance::TYPES,
            'modules' => Connaissance::MODULES,
            'documents' => GedDocument::latest('id')->limit(50)->get(['id', 'titre', 'reference']),
        ];
    }
}
