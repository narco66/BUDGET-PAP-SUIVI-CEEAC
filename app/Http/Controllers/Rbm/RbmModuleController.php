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

        $toutes = $this->service->matriceRbm($exercice);

        // Filtres (la matrice d'un exercice tient en mémoire : quelques centaines d'activités).
        $filtres = [
            'q' => trim((string) $request->query('q', '')),
            'pilier' => (string) $request->query('pilier', ''),
            'structure' => (string) $request->query('structure', ''),
            'couverture' => (string) $request->query('couverture', ''),
        ];
        $terme = mb_strtolower($filtres['q']);
        $lignes = $toutes->filter(fn ($l) => ($terme === '' || str_contains(mb_strtolower($l['activite'].' '.$l['intitule'].' '.$l['sous_produit'].' '.$l['produit']), $terme))
            && ($filtres['pilier'] === '' || $l['pilier'] === $filtres['pilier'])
            && ($filtres['structure'] === '' || $l['structure'] === $filtres['structure'])
            && match ($filtres['couverture']) {
                'sans_tache' => $l['nb_taches'] === 0,
                'sans_iov' => $l['nb_indicateurs'] === 0,
                'sans_montant' => $l['montant'] <= 0,
                default => true,
            })->values();

        $indicateurs = [
            'activites' => $lignes->count(),
            'montant' => $lignes->sum('montant'),
            'ceeac' => $lignes->sum('montant_ceeac'),
            'ptf' => $lignes->sum('montant_ptf'),
            'taches' => $lignes->sum('nb_taches'),
            'sans_tache' => $lignes->where('nb_taches', 0)->count(),
            'sans_iov' => $lignes->where('nb_indicateurs', 0)->count(),
            'couverture_iov' => $lignes->count() ? (int) round($lignes->where('nb_indicateurs', '>', 0)->count() / $lignes->count() * 100) : 0,
        ];

        return view('rbm.matrice', [
            'exercice' => $exercice,
            'lignes' => $lignes,
            'parPilier' => $lignes->groupBy('pilier'),
            'indicateurs' => $indicateurs,
            'filtres' => $filtres,
            'piliers' => $toutes->unique('pilier')->mapWithKeys(fn ($l) => [$l['pilier'] => $l['pilier_intitule']]),
            'structures' => $toutes->whereNotNull('structure')->unique('structure')->sortBy('structure')->mapWithKeys(fn ($l) => [$l['structure'] => $l['structure_libelle']]),
            'total' => $toutes->count(),
        ]);
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
