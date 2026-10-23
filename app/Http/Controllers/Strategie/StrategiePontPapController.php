<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Services\Strategie\StrategiePapLiaisonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategiePontPapController extends Controller
{
    public function __construct(private readonly StrategiePapLiaisonService $liaison)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProgrammationAnnuelle::class);

        $exerciceId = $request->integer('exercice') ?: null;
        $reconciliation = $this->liaison->reconciliation($exerciceId);

        return view('strategie.pont-pap.index', [
            'reconciliation' => $reconciliation,
            'sansPap' => $this->liaison->programmationsSansPap(),
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee']),
            'exerciceId' => $exerciceId ?? $reconciliation['exercice_id'],
        ]);
    }

    public function lier(Request $request, ProgrammationAnnuelle $programmationAnnuelle): RedirectResponse
    {
        $this->authorize('update', $programmationAnnuelle);

        $request->validate(['pap_id' => ['nullable', 'exists:paps,id']]);
        $this->liaison->lierProgrammation(
            $programmationAnnuelle,
            $request->integer('pap_id') ?: null
        );

        return back()->with('succes', 'Programmation rattachée au PAP.');
    }

    public function synchroniser(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('strategie.update'), 403);

        $exerciceId = $request->integer('exercice');
        abort_unless($exerciceId, 422);

        $count = $this->liaison->synchroniserLiaisons($exerciceId);

        return back()->with('succes', "{$count} programmation(s) synchronisée(s) avec le PAP.");
    }
}
