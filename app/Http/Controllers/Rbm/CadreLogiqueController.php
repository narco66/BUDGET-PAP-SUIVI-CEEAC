<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Http\Requests\CadreLogiqueRequest;
use App\Models\CadreLogique;
use App\Models\Exercice;
use App\Services\Rbm\RbmReferentielService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Édition documentée du cadre logique (phase E6). */
class CadreLogiqueController extends Controller
{
    public function __construct(private readonly RbmReferentielService $service)
    {
    }

    public function edit(Request $request, Exercice $exercice): View
    {
        $this->authorize('update', CadreLogique::class);
        abort_unless($request->user()->can('rbm.voir'), 403);

        $edition = $this->service->cadreLogiquePourEdition($exercice);

        return view('rbm.cadre-logique-edit', [
            'exercice' => $exercice,
            'document' => $edition['document'],
            'piliers' => $edition['piliers'],
        ]);
    }

    public function update(CadreLogiqueRequest $request, Exercice $exercice): RedirectResponse
    {
        $this->service->mettreAJourCadreLogique($exercice, $request->validated(), $request->user());

        return redirect()
            ->route('rbm.cadre-logique', $exercice)
            ->with('succes', 'Cadre logique enregistré.');
    }
}
