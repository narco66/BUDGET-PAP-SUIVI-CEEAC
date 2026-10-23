<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Rbm\Niveaux;
use App\Services\Rbm\RbmReferentielService;
use App\Services\Reporting\GouvernanceExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Exports PDF des catalogues RBM/GAR (phase 3) + archivage gouverné (P0). */
class RbmPdfController extends Controller
{
    public function __construct(
        private readonly RbmReferentielService $service,
        private readonly GouvernanceExportService $gouvernance,
    ) {
    }

    public function matrice(Request $request, Exercice $exercice): Response
    {
        abort_unless($request->user()->can('rbm.generer_pdf'), 403);

        return $this->telecharger($this->pdfMatrice($exercice), 'matrice-rbm-'.$exercice->annee.'.pdf');
    }

    public function catalogue(Request $request, Exercice $exercice, string $niveau): Response
    {
        abort_unless($request->user()->can('rbm.generer_pdf'), 403);
        abort_unless(Niveaux::existe($niveau), 404);

        return $this->telecharger(
            $this->pdfCatalogue($exercice, $niveau),
            'catalogue-'.str_replace('_', '-', $niveau).'-'.$exercice->annee.'.pdf',
        );
    }

    public function indicateurs(Request $request, Exercice $exercice): Response
    {
        abort_unless($request->user()->can('rbm.generer_pdf'), 403);

        return $this->telecharger($this->pdfIndicateurs($exercice), 'catalogue-indicateurs-'.$exercice->annee.'.pdf');
    }

    public function cadreLogique(Request $request, Exercice $exercice): Response
    {
        abort_unless(
            $request->user()->can('rbm.generer_pdf') || $request->user()->can('cadre_logique.voir'),
            403
        );

        return $this->telecharger($this->pdfCadreLogique($exercice), 'cadre-logique-'.$exercice->annee.'.pdf');
    }

    public function theorieChangement(Request $request, Exercice $exercice): Response
    {
        abort_unless($request->user()->can('rbm.generer_pdf'), 403);

        return $this->telecharger($this->pdfTheorieChangement($exercice), 'theorie-changement-'.$exercice->annee.'.pdf');
    }

    /**
     * Archive un export PDF RBM comme rapport officiel gouverné (versioning, hash, QR, GED, audit),
     * sans altérer l'export rapide. $jeu ∈ {matrice, cadre-logique, indicateurs} ou un niveau RBM.
     */
    public function archiver(Request $request, Exercice $exercice, string $jeu): RedirectResponse
    {
        abort_unless($request->user()->can('rbm.generer_pdf'), 403);

        [$type, $titre, $contenu] = $this->jeuPdf($exercice, $jeu);

        $rapport = $this->gouvernance->archiver(
            $type,
            $titre.' — '.$exercice->annee,
            $exercice,
            'pdf',
            $contenu,
            $request->user(),
        );

        return back()->with(
            'succes',
            "Rapport archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    /**
     * Résout (type gouverné, libellé, binaire PDF) pour un jeu PDF RBM.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function jeuPdf(Exercice $exercice, string $jeu): array
    {
        return match (true) {
            $jeu === 'matrice' => ['export_rbm_matrice_pdf', 'Matrice RBM/GAR', $this->pdfMatrice($exercice)],
            $jeu === 'cadre-logique' => ['export_rbm_cadre_logique_pdf', 'Cadre logique', $this->pdfCadreLogique($exercice)],
            $jeu === 'indicateurs' => ['export_rbm_indicateurs_pdf', 'Catalogue des indicateurs', $this->pdfIndicateurs($exercice)],
            $jeu === 'theorie-changement' => ['export_rbm_theorie_changement_pdf', 'Théorie du changement', $this->pdfTheorieChangement($exercice)],
            Niveaux::existe($jeu) => ['export_rbm_catalogue_'.$jeu.'_pdf', 'Catalogue — '.Niveaux::get($jeu)['label'], $this->pdfCatalogue($exercice, $jeu)],
            default => abort(404),
        };
    }

    private function pdfMatrice(Exercice $exercice): string
    {
        return Pdf::loadView('rbm.pdf.matrice', [
            'exercice' => $exercice,
            'lignes' => $this->service->matriceRbm($exercice),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function pdfCatalogue(Exercice $exercice, string $niveau): string
    {
        return Pdf::loadView('rbm.pdf.catalogue', [
            'exercice' => $exercice,
            'niveau' => $niveau,
            'label' => Niveaux::get($niveau)['label'],
            'elements' => $this->service->catalogueNiveau($exercice, $niveau),
        ])->setPaper('a4', 'portrait')->output();
    }

    private function pdfIndicateurs(Exercice $exercice): string
    {
        return Pdf::loadView('rbm.pdf.indicateurs', [
            'exercice' => $exercice,
            'indicateurs' => $this->service->catalogueIndicateurs($exercice),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function pdfTheorieChangement(Exercice $exercice): string
    {
        return Pdf::loadView('rbm.pdf.theorie-changement', [
            'exercice' => $exercice,
            'tc' => $this->service->theorieChangement($exercice),
        ])->setPaper('a4', 'portrait')->output();
    }

    private function pdfCadreLogique(Exercice $exercice): string
    {
        $document = $this->service->documentCadreLogique($exercice)
            ?? $this->service->synchroniserCadreLogique($exercice);

        return Pdf::loadView('rbm.pdf.cadre-logique', [
            'exercice' => $exercice,
            'document' => $document,
            'cadre' => $this->service->cadreLogique($exercice),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function telecharger(string $contenu, string $nom): Response
    {
        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }
}
