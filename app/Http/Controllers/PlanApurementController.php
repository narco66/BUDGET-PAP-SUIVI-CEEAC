<?php

namespace App\Http\Controllers;

use App\Models\AppelFonds;
use App\Models\EcheanceApurement;
use App\Models\PlanApurement;
use App\Services\Recettes\PlanApurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Phase R7 — plans d'apurement des arriérés (échéancier de règlement). */
class PlanApurementController extends Controller
{
    public function __construct(private readonly PlanApurementService $service)
    {
    }

    public function store(Request $request, AppelFonds $appelsFond): RedirectResponse
    {
        $this->authorize('recouvrer', $appelsFond);

        $data = $request->validate([
            'nombre_echeances' => ['required', 'integer', 'min:1', 'max:60'],
            'date_debut' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $plan = $this->service->creer($appelsFond, $data, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('plans-apurement.show', $plan)->with('succes', 'Plan d\'apurement établi.');
    }

    public function show(PlanApurement $planApurement): View
    {
        abort_unless(request()->user()->can('recette.voir'), 403);

        $planApurement->load(['appel.etatMembre', 'appel.sourceFinancement', 'echeances', 'etabliPar']);

        return view('plans-apurement.show', ['plan' => $planApurement]);
    }

    public function reglerEcheance(Request $request, EcheanceApurement $echeance): RedirectResponse
    {
        abort_unless($request->user()->can('recette.recouvrement'), 403);

        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->reglerEcheance($echeance, (float) $data['montant'], $data['observations'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Règlement de l\'échéance enregistré.');
    }

    public function annuler(PlanApurement $planApurement): RedirectResponse
    {
        abort_unless(request()->user()->can('recette.recouvrement'), 403);

        try {
            $this->service->annuler($planApurement);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('appels-fonds.show', $planApurement->appel_fonds_id)->with('succes', 'Plan d\'apurement annulé.');
    }
}
