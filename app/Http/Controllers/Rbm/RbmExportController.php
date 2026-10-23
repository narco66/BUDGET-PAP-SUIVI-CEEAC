<?php

namespace App\Http\Controllers\Rbm;

use App\Exports\Rbm\CadreLogiqueExport;
use App\Exports\Rbm\IndicateursCatalogueExport;
use App\Exports\Rbm\MatriceRbmExport;
use App\Exports\Rbm\NiveauRbmExport;
use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Rbm\Niveaux;
use App\Services\Reporting\GouvernanceExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RbmExportController extends Controller
{
    public function __construct(private readonly GouvernanceExportService $gouvernance)
    {
    }

    public function matrice(Request $request, Exercice $exercice): BinaryFileResponse
    {
        abort_unless($request->user()->can('rbm.exporter'), 403);

        return Excel::download(new MatriceRbmExport($exercice), 'matrice-rbm-'.$exercice->annee.'.xlsx');
    }

    public function niveau(Request $request, Exercice $exercice, string $niveau): BinaryFileResponse
    {
        abort_unless($request->user()->can('rbm.exporter'), 403);
        abort_unless(Niveaux::existe($niveau), 404);

        $label = Niveaux::get($niveau)['label'];

        return Excel::download(
            new NiveauRbmExport($exercice, $niveau),
            'catalogue-'.str_replace('_', '-', $niveau).'-'.$exercice->annee.'.xlsx'
        );
    }

    public function indicateurs(Request $request, Exercice $exercice): BinaryFileResponse
    {
        abort_unless($request->user()->can('rbm.exporter') || $request->user()->can('indicateur.exporter'), 403);

        return Excel::download(
            new IndicateursCatalogueExport($exercice),
            'catalogue-indicateurs-'.$exercice->annee.'.xlsx'
        );
    }

    public function cadreLogique(Request $request, Exercice $exercice): BinaryFileResponse
    {
        abort_unless(
            $request->user()->can('rbm.exporter') || $request->user()->can('cadre_logique.voir'),
            403
        );

        return Excel::download(
            new CadreLogiqueExport($exercice),
            'cadre-logique-'.$exercice->annee.'.xlsx'
        );
    }

    /**
     * Archive un export Excel RBM comme rapport officiel gouverné (versioning, hash, QR, GED, audit),
     * sans altérer l'export rapide. $jeu ∈ {matrice, cadre-logique, indicateurs} ou un niveau RBM.
     */
    public function archiver(Request $request, Exercice $exercice, string $jeu): RedirectResponse
    {
        abort_unless($request->user()->can('rbm.exporter'), 403);

        [$type, $titre, $instance] = $this->jeuExcel($exercice, $jeu);
        $contenu = Excel::raw($instance, ExcelFormat::XLSX);

        $rapport = $this->gouvernance->archiver(
            $type,
            $titre.' — '.$exercice->annee,
            $exercice,
            'excel',
            $contenu,
            $request->user(),
        );

        return back()->with(
            'succes',
            "Export archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    /**
     * Résout (type gouverné, libellé, instance d'export) pour un jeu Excel RBM.
     *
     * @return array{0: string, 1: string, 2: object}
     */
    private function jeuExcel(Exercice $exercice, string $jeu): array
    {
        return match (true) {
            $jeu === 'matrice' => ['export_rbm_matrice', 'Matrice RBM/GAR', new MatriceRbmExport($exercice)],
            $jeu === 'cadre-logique' => ['export_rbm_cadre_logique', 'Cadre logique', new CadreLogiqueExport($exercice)],
            $jeu === 'indicateurs' => ['export_rbm_indicateurs', 'Catalogue des indicateurs', new IndicateursCatalogueExport($exercice)],
            Niveaux::existe($jeu) => ['export_rbm_niveau_'.$jeu, 'Catalogue — '.Niveaux::get($jeu)['label'], new NiveauRbmExport($exercice, $jeu)],
            default => abort(404),
        };
    }
}
