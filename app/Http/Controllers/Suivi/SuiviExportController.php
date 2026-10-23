<?php

namespace App\Http\Controllers\Suivi;

use App\Exports\Suivi\CollectesCampagneExport;
use App\Http\Controllers\Controller;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Services\Reporting\GouvernanceExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class SuiviExportController extends Controller
{
    public function __construct(private readonly GouvernanceExportService $gouvernance)
    {
    }

    public function collectes(CampagneSuivi $campagne, string $format): Response
    {
        $this->authorize('export', $campagne);
        abort_unless(in_array($format, ['pdf', 'xlsx', 'csv'], true), 404);

        [$mime, $ext] = match ($format) {
            'pdf' => ['application/pdf', 'pdf'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
            'csv' => ['text/csv', 'csv'],
        };

        return response($this->rendre($campagne, $format), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="collectes_'.$campagne->periode.'.'.$ext.'"',
        ]);
    }

    /**
     * Archive l'état des collectes d'une campagne comme rapport officiel gouverné (versioning, hash,
     * QR, GED, audit), sans altérer l'export rapide. $format ∈ {pdf, xlsx, csv}.
     */
    public function archiver(Request $request, CampagneSuivi $campagne, string $format): RedirectResponse
    {
        $this->authorize('export', $campagne);
        abort_unless(in_array($format, ['pdf', 'xlsx', 'csv'], true), 404);

        $logique = $format === 'xlsx' ? 'excel' : $format;

        $rapport = $this->gouvernance->archiver(
            'export_suivi_collectes'.($format === 'pdf' ? '_pdf' : ''),
            'Collectes de suivi — '.$campagne->libelle,
            $campagne->exercice,
            $logique,
            $this->rendre($campagne, $format),
            $request->user(),
            ['campagne_id' => $campagne->id, 'periode' => $campagne->periode],
        );

        return back()->with(
            'succes',
            "Collectes archivées comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    /** Produit le binaire des collectes pour un format (source unique téléchargement / archivage). */
    private function rendre(CampagneSuivi $campagne, string $format): string
    {
        $collectes = $this->collectesData($campagne);

        return match ($format) {
            'pdf' => Pdf::loadView('suivi.pdf.collectes', compact('campagne', 'collectes'))->output(),
            'xlsx' => Excel::raw(new CollectesCampagneExport($collectes), ExcelFormat::XLSX),
            'csv' => Excel::raw(new CollectesCampagneExport($collectes), ExcelFormat::CSV),
            default => abort(404),
        };
    }

    private function collectesData(CampagneSuivi $campagne): Collection
    {
        return Collecte::with(['activite.structure', 'campagne'])
            ->where('campagne_id', $campagne->id)
            ->orderBy('id')
            ->get();
    }
}
