<?php

namespace App\Http\Controllers;

use App\Exports\Institution\AnnuaireInstitutionnelExport;
use App\Models\Structure;
use App\Services\Institution\InstitutionExportService;
use App\Services\Reporting\GouvernanceExportService;
use App\Support\ReferentielAdministratifAuthorization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Exports Excel/PDF du référentiel institutionnel (phase A5) + archivage gouverné (P0). */
class InstitutionExportController extends Controller
{
    public function __construct(
        private readonly InstitutionExportService $exports,
        private readonly GouvernanceExportService $gouvernance,
    ) {
    }

    public function annuaireExcel(Request $request): BinaryFileResponse
    {
        $this->autoriserExport($request);

        return Excel::download(
            new AnnuaireInstitutionnelExport($this->lignes($request)),
            'annuaire-institutionnel-'.now()->format('Ymd').'.xlsx'
        );
    }

    public function annuairePdf(Request $request): Response
    {
        $this->autoriserExport($request);

        $nom = 'annuaire-institutionnel-'.now()->format('Ymd').'.pdf';

        return response($this->renduPdf($request), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }

    /**
     * Archive l'annuaire institutionnel (Excel ou PDF) comme rapport officiel gouverné (versioning,
     * hash, QR, GED, audit), sans altérer l'export rapide. Institution = hors exercice.
     */
    public function archiver(Request $request, string $format): RedirectResponse
    {
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);
        $this->autoriserExport($request);

        $contenu = $format === 'excel'
            ? Excel::raw(new AnnuaireInstitutionnelExport($this->lignes($request)), ExcelFormat::XLSX)
            : $this->renduPdf($request);

        $rapport = $this->gouvernance->archiver(
            'export_institution_annuaire'.($format === 'pdf' ? '_pdf' : ''),
            'Annuaire institutionnel',
            null,
            $format,
            $contenu,
            $request->user(),
            array_filter([
                'type' => $request->string('type')->toString() ?: null,
                'q' => $request->string('q')->toString() ?: null,
            ]),
        );

        return back()->with(
            'succes',
            "Annuaire archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    private function lignes(Request $request): Collection
    {
        return $this->exports->annuairePourExport(
            $request->user(),
            $request->string('type')->toString() ?: null,
            $request->string('q')->toString() ?: null,
        );
    }

    private function renduPdf(Request $request): string
    {
        return Pdf::loadView('institution.pdf.annuaire', [
            'lignes' => $this->lignes($request),
            'filtreType' => $request->input('type'),
            'filtreQ' => $request->input('q'),
            'genereLe' => now(),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function autoriserExport(Request $request): void
    {
        abort_unless(ReferentielAdministratifAuthorization::peut($request->user(), 'exporter'), 403);
        $this->authorize('viewAny', Structure::class);
    }
}
