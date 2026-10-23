<?php

namespace App\Http\Controllers;

use App\Models\Pap;
use App\Services\Pap\PapExecutionFinanciereService;
use App\Services\Pap\PapIntegrationsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PapIntegrationController extends Controller
{
    public function __construct(
        private readonly PapExecutionFinanciereService $execution,
        private readonly PapIntegrationsService $integrations,
    ) {
    }

    public function executionFinanciere(Pap $pap): View
    {
        $this->authorize('view', $pap);

        $chaine = $this->execution->chainePourExercice($pap->exercice_id);
        $alignements = $this->integrations->rapportAlignements($pap->exercice_id);

        return view('paps.execution-financiere', compact('pap', 'chaine', 'alignements'));
    }

    public function synchroniserAlignements(Pap $pap): RedirectResponse
    {
        $this->authorize('gererGar', $pap);

        $count = $this->integrations->synchroniserAlignements($pap->exercice_id);

        return back()->with('succes', "{$count} nouvelle(s) liaison(s) activité ↔ programmation.");
    }
}
