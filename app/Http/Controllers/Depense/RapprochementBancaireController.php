<?php

namespace App\Http\Controllers\Depense;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Models\Paiement;
use App\Models\RapprochementBancaire;
use App\Models\RapprochementLigne;
use App\Services\Depense\RapprochementBancaireService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Rapprochement bancaire des paiements (phase D3). */
class RapprochementBancaireController extends Controller
{
    public function __construct(private readonly RapprochementBancaireService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->autoriser($request);
        $exercice = Exercice::orderByDesc('annee')->first();

        $sessions = RapprochementBancaire::with('exercice', 'createur')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->latest()
            ->paginate(15);

        return view('depense.rapprochement.index', [
            'sessions' => $sessions,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'exercice' => $exercice,
        ]);
    }

    public function create(Request $request): View
    {
        $this->autoriser($request);

        return view('depense.rapprochement.create', [
            'exercices' => Exercice::orderByDesc('annee')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autoriser($request);

        $data = $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id'],
            'intitule' => ['required', 'string', 'max:255'],
            'compte_bancaire' => ['nullable', 'string', 'max:50'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $exercice = Exercice::findOrFail($data['exercice_id']);
        $session = $this->service->creer($exercice, $data, $request->user());

        return redirect()->route('depense.rapprochement.show', $session)
            ->with('succes', 'Session de rapprochement créée.');
    }

    public function show(Request $request, RapprochementBancaire $rapprochement): View
    {
        $this->autoriser($request);
        $rapprochement->load(['lignes.paiement', 'exercice', 'createur']);

        return view('depense.rapprochement.show', [
            'session' => $rapprochement,
            'paiementsEligibles' => $this->service->paiementsEligibles($rapprochement),
        ]);
    }

    public function importer(Request $request, RapprochementBancaire $rapprochement): RedirectResponse
    {
        $this->autoriser($request);

        $data = $request->validate([
            'lignes' => ['required', 'string'],
        ]);

        $lignes = $this->parserCsv($data['lignes']);
        if ($lignes === []) {
            return back()->with('erreur', 'Aucune ligne valide à importer.');
        }

        $count = $this->service->importerLignes($rapprochement, $lignes);

        return back()->with('succes', "{$count} ligne(s) importée(s).");
    }

    public function auto(RapprochementBancaire $rapprochement): RedirectResponse
    {
        $this->autoriser(request());
        $matches = $this->service->rapprocherAuto($rapprochement);

        return back()->with('succes', "{$matches} rapprochement(s) automatique(s).");
    }

    public function lier(Request $request, RapprochementBancaire $rapprochement): RedirectResponse
    {
        $this->autoriser($request);

        $data = $request->validate([
            'ligne_id' => ['required', 'exists:rapprochement_lignes,id'],
            'paiement_id' => ['required', 'exists:paiements,id'],
        ]);

        $ligne = RapprochementLigne::findOrFail($data['ligne_id']);
        $paiement = Paiement::findOrFail($data['paiement_id']);

        try {
            $this->service->rapprocherManuel($rapprochement, $ligne, $paiement);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Ligne rapprochée.');
    }

    public function cloturer(RapprochementBancaire $rapprochement): RedirectResponse
    {
        $this->autoriser(request());

        try {
            $this->service->cloturer($rapprochement);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Session clôturée.');
    }

    /** @return list<array{date_operation:string,libelle:string,reference_bancaire?:string,montant:float}> */
    private function parserCsv(string $contenu): array
    {
        $lignes = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($contenu)) as $row) {
            if ($row === '' || str_starts_with($row, '#')) {
                continue;
            }
            $cols = str_getcsv($row, ';');
            if (count($cols) < 3) {
                $cols = str_getcsv($row, ',');
            }
            if (count($cols) < 3) {
                continue;
            }

            $lignes[] = count($cols) >= 4
                ? [
                    'date_operation' => $cols[0],
                    'libelle' => $cols[1],
                    'reference_bancaire' => $cols[2] ?: null,
                    'montant' => (float) str_replace([' ', ','], ['', '.'], $cols[3]),
                ]
                : [
                    'date_operation' => $cols[0],
                    'libelle' => $cols[1],
                    'montant' => (float) str_replace([' ', ','], ['', '.'], $cols[2]),
                ];
        }

        return $lignes;
    }

    private function autoriser(Request $request): void
    {
        abort_unless(
            $request->user()->can('depenses.admin')
            || $request->user()->can('paiement.valider')
            || $request->user()->can('depenses.view'),
            403
        );
    }
}
