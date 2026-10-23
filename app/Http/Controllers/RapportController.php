<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pont de compatibilité : les anciennes routes /rapports redirigent vers le module Reporting unifié.
 *
 * @deprecated Utiliser reporting.* directement.
 */
class RapportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rapport.voir'), 403);

        return redirect()
            ->route('reporting.index', ['exercice' => $this->exerciceId($request)])
            ->with('succes', 'Les rapports de synthèse sont désormais centralisés dans Reporting & Analyse.');
    }

    public function executionBudgetairePdf(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rapport.exporter'), 403);

        return redirect()->route('reporting.generer', [
            'type' => 'execution_synthese',
            'exercice' => $this->exerciceId($request),
            'format' => 'pdf',
        ]);
    }

    public function executionBudgetaireExcel(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rapport.exporter'), 403);

        return redirect()->route('reporting.generer', [
            'type' => 'execution_synthese',
            'exercice' => $this->exerciceId($request),
            'format' => 'excel',
        ]);
    }

    public function papPdf(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rapport.exporter'), 403);

        return redirect()->route('reporting.generer', [
            'type' => 'pap',
            'exercice' => $this->exerciceId($request),
            'format' => 'pdf',
        ]);
    }

    private function exerciceId(Request $request): int
    {
        if ($request->filled('exercice')) {
            return (int) $request->exercice;
        }

        return (Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->firstOrFail())->id;
    }
}
