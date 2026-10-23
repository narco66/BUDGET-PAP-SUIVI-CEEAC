<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\ReferentielFinancierService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hub du référentiel financier CEEAC (Phase D). */
class ReferentielFinancierController extends Controller
{
    public function __construct(private readonly ReferentielFinancierService $referentiel)
    {
    }

    public function hub(Request $request): View
    {
        abort_unless($this->peutConsulter($request), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice')) ?? ExerciceService::courant();
        $stats = $this->referentiel->indicateurs($exercice?->id);

        return view('referentiel.hub', compact('exercices', 'exercice', 'stats'));
    }

    private function peutConsulter(Request $request): bool
    {
        $u = $request->user();

        return $u->can('exercice.voir')
            || $u->can('source_financement.voir')
            || $u->can('partenaire_financier.voir')
            || $u->can('nomenclature.voir')
            || $u->can('ligne_budgetaire.voir');
    }
}
