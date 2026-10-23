<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\ConventionFinancement;
use App\Models\DecaissementConvention;
use App\Services\Recettes\DecaissementConventionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Phase R8 — décaissements PTF rattachés aux activités du PAP. */
class DecaissementConventionController extends Controller
{
    public function __construct(private readonly DecaissementConventionService $service)
    {
    }

    public function show(ConventionFinancement $convention): View
    {
        $this->authorize('view', $convention);

        $convention->load(['partenaire', 'sourceFinancement', 'exercice', 'decaissements.activite', 'decaissements.saisiPar']);

        // Décaissements exécutés agrégés par activité financée (liaison PAP).
        $parActivite = $convention->decaissements
            ->where('statut', 'execute')
            ->groupBy('activite_id')
            ->map(fn ($grp) => (object) [
                'activite' => $grp->first()->activite,
                'montant' => (float) $grp->sum('montant_xaf'),
            ])
            ->values();

        return view('referentiel.conventions.show', [
            'convention' => $convention,
            'parActivite' => $parActivite,
            'activites' => Activite::orderBy('code')->get(['id', 'code', 'intitule']),
        ]);
    }

    public function store(Request $request, ConventionFinancement $convention): RedirectResponse
    {
        $this->authorize('update', $convention);

        $data = $request->validate([
            'reference' => ['required', 'string', 'max:60', Rule::unique('decaissements_convention', 'reference')],
            'objet' => ['required', 'string', 'max:255'],
            'activite_id' => ['nullable', 'exists:activites,id'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'devise' => ['required', 'string', 'size:3'],
            'taux_change' => ['required', 'numeric', 'min:0.000001'],
            'date_decaissement' => ['required', 'date'],
            'statut' => ['required', Rule::in(array_keys(DecaissementConvention::STATUTS))],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->enregistrer($convention, $data, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('conventions.show', $convention)->with('succes', 'Décaissement enregistré.');
    }

    public function annuler(DecaissementConvention $decaissement): RedirectResponse
    {
        $this->authorize('update', $decaissement->convention);

        try {
            $this->service->annuler($decaissement);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Décaissement annulé.');
    }
}
