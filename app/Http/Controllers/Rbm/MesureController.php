<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbm\MesureRequest;
use App\Models\Indicateur;
use App\Models\Mesure;
use App\Services\Rbm\MesureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MesureController extends Controller
{
    public function __construct(private readonly MesureService $service)
    {
    }

    public function store(MesureRequest $request, Indicateur $indicateur): RedirectResponse
    {
        $this->authorize('create', Mesure::class);
        $this->service->enregistrer($indicateur, $request->validated(), $request->user()->id);

        return back()->with('succes', 'Mesure enregistrée.');
    }

    public function update(MesureRequest $request, Mesure $mesure): RedirectResponse
    {
        $this->authorize('update', $mesure);
        $this->service->modifier($mesure, $request->validated(), $request->user()->id);

        return back()->with('succes', 'Mesure mise à jour.');
    }

    public function destroy(Request $request, Mesure $mesure): RedirectResponse
    {
        $this->authorize('delete', $mesure);
        $mesure->delete();

        return back()->with('succes', 'Mesure supprimée.');
    }

    public function verifier(Request $request, Mesure $mesure): RedirectResponse
    {
        $this->authorize('verify', $mesure);
        $this->service->verifier($mesure, $request->user()->id);

        return back()->with('succes', 'Mesure vérifiée.');
    }

    public function valider(Request $request, Mesure $mesure): RedirectResponse
    {
        $this->authorize('validateMeasure', $mesure);
        $this->service->valider($mesure, $request->user()->id);

        return back()->with('succes', 'Mesure validée.');
    }
}
