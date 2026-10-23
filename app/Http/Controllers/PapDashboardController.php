<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Models\Pap;
use App\Services\Pap\PapPilotageService;
use App\Services\Pap\PapReplacementImportService;
use App\Services\TableauBordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PapDashboardController extends Controller
{
    public function __construct(
        private readonly PapPilotageService $pilotage,
        private readonly TableauBordService $tableauBord,
        private readonly PapReplacementImportService $replacementImport,
    ) {
    }

    /** Tableau de pilotage PAP unifié (KPI, drill-down pilier → produit → activité, alertes). */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Pap::class);

        [$exercice, $pap] = $this->resoudreContexte($request);
        abort_unless($exercice, 404, 'Exercice introuvable.');

        $pilotage = $this->pilotage->tableau($exercice->id);
        $exercices = Exercice::orderByDesc('annee')->get(['id', 'annee', 'statut']);
        $paps = Pap::with('exercice')->orderByDesc('exercice_id')->get(['id', 'exercice_id', 'libelle', 'statut']);

        $chartPiliers = $pilotage['piliers']->map(fn (array $p) => [
            'x' => $p['code'],
            'label' => \Illuminate\Support\Str::limit($p['intitule'], 32),
            'physique' => $p['taux_physique'],
            'financier' => $p['taux_financier'],
            'ceeac' => $p['montant_ceeac'],
            'ptf' => $p['montant_ptf'],
        ]);

        return view('paps.dashboard', [
            'exercice' => $exercice,
            'pap' => $pap,
            'pilotage' => $pilotage,
            'exercices' => $exercices,
            'paps' => $paps,
            'feux' => $this->tableauBord->feuxIndicateurs($exercice->id),
            'chartPiliers' => $chartPiliers,
            'pilierFiltre' => $request->string('pilier')->toString() ?: null,
        ]);
    }

    public function importer(Request $request): RedirectResponse
    {
        $exercice = Exercice::findOrFail($request->integer('exercice_id'));
        $pap = Pap::firstWhere('exercice_id', $exercice->id);

        if ($pap) {
            $this->authorize('update', $pap);
        } else {
            $this->authorize('create', Pap::class);
        }

        $data = $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id'],
            'fichier_pap' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'modele_pap' => ['nullable', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        try {
            $resultat = $this->replacementImport->remplacer(
                $data['fichier_pap']->getRealPath(),
                $exercice->id,
                isset($data['modele_pap']) ? $data['modele_pap']->getRealPath() : null,
                auth()->id(),
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('paps.dashboard', ['exercice' => $exercice->annee])
                ->with('erreur', 'Import PAP impossible : '.$e->getMessage());
        }

        $creation = $resultat['creation'];
        $message = sprintf(
            'PAP importé : %d piliers, %d axes, %d produits, %d sous-produits, %d activités. Snapshot historique #%d.',
            $creation['piliers'],
            $creation['axes'],
            $creation['produits'],
            $creation['sous_produits'],
            $creation['activites'],
            $resultat['historique_id'],
        );

        if (($creation['lignes_budgetaires_introuvables'] ?? 0) > 0) {
            $message .= ' '.$creation['lignes_budgetaires_introuvables'].' ligne(s) budget non rapprochée(s).';
        }

        return redirect()
            ->route('paps.dashboard', ['exercice' => $exercice->annee])
            ->with('succes', $message);
    }

    /** @return array{0: ?Exercice, 1: ?Pap} */
    private function resoudreContexte(Request $request): array
    {
        if ($request->filled('pap')) {
            $pap = Pap::with('exercice')->findOrFail($request->integer('pap'));

            return [$pap->exercice, $pap];
        }

        if ($request->filled('exercice')) {
            $opt = $request->input('exercice');
            $exercice = Exercice::where('id', $opt)->orWhere('annee', $opt)->first();
            $pap = $exercice ? Pap::firstWhere('exercice_id', $exercice->id) : null;

            return [$exercice, $pap];
        }

        $pap = Pap::with('exercice')->whereHas('exercice', fn ($q) => $q->where('statut', 'ouvert'))
            ->latest('exercice_id')
            ->first()
            ?? Pap::with('exercice')->latest('exercice_id')->first();

        return [$pap?->exercice, $pap];
    }
}
