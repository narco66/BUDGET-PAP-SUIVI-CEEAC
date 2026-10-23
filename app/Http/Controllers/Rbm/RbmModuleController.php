<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Services\Rbm\RbmReferentielService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Vues synthétiques du Module 2 RBM/GAR (matrice, cadre logique, chaîne de résultats). */
class RbmModuleController extends Controller
{
    public function __construct(private readonly RbmReferentielService $service)
    {
    }

    public function hub(Request $request): View
    {
        abort_unless($request->user()->can('rbm.voir'), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? Exercice::firstWhere('statut', 'ouvert')
            ?? $exercices->first();

        return view('rbm.hub', compact('exercices', 'exercice'));
    }

    public function matrice(Request $request, Exercice $exercice): View
    {
        abort_unless($request->user()->can('rbm.voir'), 403);

        $lignes = $this->service->matriceRbm($exercice);

        return view('rbm.matrice', compact('exercice', 'lignes'));
    }

    public function cadreLogique(Request $request, Exercice $exercice): View
    {
        abort_unless($request->user()->can('rbm.voir'), 403);

        $cadre = $this->service->cadreLogique($exercice);
        $document = $this->service->synchroniserCadreLogique($exercice);

        return view('rbm.cadre-logique', compact('exercice', 'cadre', 'document'));
    }

    public function chaineResultats(Request $request, Exercice $exercice): View
    {
        abort_unless($request->user()->can('rbm.voir'), 403);

        $chaine = $this->service->chaineResultats($exercice);
        $this->service->synchroniserChaineResultats($exercice);

        return view('rbm.chaine-resultats', compact('exercice', 'chaine'));
    }

    public function theorieChangement(Request $request, Exercice $exercice): View
    {
        abort_unless($request->user()->can('rbm.voir'), 403);

        return view('rbm.theorie-changement', [
            'exercice' => $exercice,
            'tc' => $this->service->theorieChangement($exercice),
        ]);
    }

    public function synthesePdf(Request $request, Exercice $exercice): Response
    {
        abort_unless($request->user()->can('rbm.generer_pdf'), 403);

        $pdf = Pdf::loadView('rbm.pdf.synthese', [
            'exercice' => $exercice,
            'matrice' => $this->service->matriceRbm($exercice),
            'cadre' => $this->service->cadreLogique($exercice),
            'chaine' => $this->service->chaineResultats($exercice),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('synthese-rbm-'.$exercice->annee.'.pdf');
    }
}
