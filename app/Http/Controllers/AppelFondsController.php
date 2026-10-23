<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppelFondsRequest;
use App\Models\AppelFonds;
use App\Models\Contribution;
use App\Models\EtatMembre;
use App\Models\Exercice;
use App\Models\SourceFinancement;
use App\Services\Recettes\AppelFondsService;
use App\Services\Recettes\RelanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AppelFondsController extends Controller
{
    public function __construct(private readonly AppelFondsService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AppelFonds::class);

        $exercice = $request->filled('exercice')
            ? Exercice::find($request->exercice)
            : (Exercice::actif()->first() ?? Exercice::orderByDesc('annee')->first());

        // Tableau de bord des contributions par État membre (appelé / réglé / arriéré / taux).
        $parEtat = AppelFonds::query()
            ->selectRaw('etat_membre_id, SUM(montant_appele) appele, SUM(montant_regle) regle')
            ->when($exercice, fn ($q) => $q->where('exercice_id', $exercice->id))
            ->whereNotNull('etat_membre_id')
            ->where('statut', '!=', 'annule')
            ->groupBy('etat_membre_id')
            ->with('etatMembre')
            ->get()
            ->keyBy('etat_membre_id');

        $etats = EtatMembre::orderBy('pays')->get();

        $appels = AppelFonds::query()
            ->with(['exercice', 'etatMembre', 'sourceFinancement', 'emisPar'])
            ->when($exercice, fn ($q) => $q->where('exercice_id', $exercice->id))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('date_emission')
            ->paginate(20)
            ->withQueryString();

        return view('appels-fonds.index', [
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'parEtat' => $parEtat,
            'etats' => $etats,
            'appels' => $appels,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AppelFonds::class);

        return view('appels-fonds.create', [
            'appel' => new AppelFonds(['date_emission' => now()->toDateString(), 'taux_penalite' => 0]),
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'etats' => EtatMembre::orderBy('pays')->get(),
            'sources' => SourceFinancement::where('actif', true)->orderBy('libelle')->get(),
            'contributions' => Contribution::with(['etatMembre', 'sourceFinancement'])->get(),
        ]);
    }

    public function store(AppelFondsRequest $request): RedirectResponse
    {
        $this->authorize('create', AppelFonds::class);

        try {
            $appel = $this->service->emettre($request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('appels-fonds.show', $appel)->with('succes', 'Appel de fonds émis.');
    }

    public function show(AppelFonds $appelsFond): View
    {
        $this->authorize('view', $appelsFond);

        $appelsFond->load(['exercice', 'etatMembre', 'sourceFinancement', 'emisPar', 'pieces', 'relances.emisePar']);

        return view('appels-fonds.show', ['appel' => $appelsFond]);
    }

    public function relancer(Request $request, AppelFonds $appelsFond, RelanceService $relances): RedirectResponse
    {
        $this->authorize('recouvrer', $appelsFond);

        $data = $request->validate([
            'canal' => ['required', 'in:'.implode(',', array_keys(\App\Models\Relance::CANAUX))],
            'destinataire' => ['nullable', 'string', 'max:255'],
            'objet' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $relances->relancer($appelsFond, $data, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Relance enregistrée.');
    }

    public function recouvrer(Request $request, AppelFonds $appelsFond): RedirectResponse
    {
        $this->authorize('recouvrer', $appelsFond);

        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->enregistrerRecouvrement($appelsFond, (float) $data['montant'], $data['observations'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Recouvrement enregistré.');
    }

    public function annuler(AppelFonds $appelsFond): RedirectResponse
    {
        $this->authorize('annuler', $appelsFond);

        try {
            $this->service->annuler($appelsFond);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Appel de fonds annulé.');
    }

    public function destroy(AppelFonds $appelsFond): RedirectResponse
    {
        $this->authorize('delete', $appelsFond);

        try {
            $appelsFond->delete();
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('appels-fonds.index')->with('succes', 'Appel de fonds supprimé.');
    }
}
