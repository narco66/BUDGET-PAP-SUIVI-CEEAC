<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbm\ReferentielCatalogRequest;
use App\Models\SourceVerification;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** CRUD des catalogues référentiels RBM (types, unités, sources de vérification). */
class RbmCatalogController extends Controller
{
    /** @var array<string,array{model:class-string,label:string}> */
    private const CATALOGUES = [
        'types-indicateurs' => ['model' => TypeIndicateur::class, 'label' => 'Types d\'indicateurs'],
        'unites-mesure' => ['model' => UniteMesure::class, 'label' => 'Unités de mesure'],
        'sources-verification' => ['model' => SourceVerification::class, 'label' => 'Sources de vérification'],
    ];

    public function index(Request $request, string $catalogue): View
    {
        $cfg = $this->cfg($catalogue);
        $this->authorize('viewAny', $cfg['model']);

        $items = $cfg['model']::query()
            ->recherche($request->input('q'))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderBy('ordre')->orderBy('code')
            ->paginate(20)->withQueryString();

        return view('rbm.catalogues.index', [
            'catalogue' => $catalogue,
            'label' => $cfg['label'],
            'items' => $items,
        ]);
    }

    public function create(string $catalogue): View
    {
        $cfg = $this->cfg($catalogue);
        $this->authorize('create', $cfg['model']);

        return view('rbm.catalogues.form', ['catalogue' => $catalogue, 'label' => $cfg['label'], 'item' => null]);
    }

    public function store(ReferentielCatalogRequest $request, string $catalogue): RedirectResponse
    {
        $cfg = $this->cfg($catalogue);
        $this->authorize('create', $cfg['model']);

        $cfg['model']::create($request->validated());

        return redirect()->route('rbm.catalogues.index', $catalogue)->with('succes', 'Élément créé.');
    }

    public function edit(string $catalogue, int $item): View
    {
        $cfg = $this->cfg($catalogue);
        $model = $cfg['model']::findOrFail($item);
        $this->authorize('update', $model);

        return view('rbm.catalogues.form', ['catalogue' => $catalogue, 'label' => $cfg['label'], 'item' => $model]);
    }

    public function update(ReferentielCatalogRequest $request, string $catalogue, int $item): RedirectResponse
    {
        $cfg = $this->cfg($catalogue);
        $model = $cfg['model']::findOrFail($item);
        $this->authorize('update', $model);
        $model->update($request->validated());

        return redirect()->route('rbm.catalogues.index', $catalogue)->with('succes', 'Élément mis à jour.');
    }

    public function destroy(string $catalogue, int $item): RedirectResponse
    {
        $cfg = $this->cfg($catalogue);
        $model = $cfg['model']::findOrFail($item);
        $this->authorize('delete', $model);
        $model->delete();

        return back()->with('succes', 'Élément archivé (suppression logique).');
    }

    private function cfg(string $catalogue): array
    {
        abort_unless(isset(self::CATALOGUES[$catalogue]), 404);

        return self::CATALOGUES[$catalogue];
    }
}
