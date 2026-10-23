<?php

namespace App\Http\Controllers;

use App\Exports\Referentiel\ConventionsFinancementExport;
use App\Exports\Referentiel\PartenairesFinancierExport;
use App\Exports\Referentiel\SourcesFinancementExport;
use App\Models\Exercice;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\ReferentielFinancierService;
use App\Services\Reporting\GouvernanceExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Exports Excel/PDF du référentiel financier (Phase D) + archivage gouverné (P0). */
class ReferentielFinancierExportController extends Controller
{
    /** Jeux Excel archivables → type gouverné, libellé, permission requise. */
    private const EXCELS = [
        'sources' => ['type' => 'export_referentiel_sources', 'titre' => 'Sources de financement', 'perm' => 'source_financement.exporter'],
        'partenaires' => ['type' => 'export_referentiel_partenaires', 'titre' => 'Partenaires techniques et financiers', 'perm' => 'partenaire_financier.exporter'],
        'conventions' => ['type' => 'export_referentiel_conventions', 'titre' => 'Conventions de financement', 'perm' => 'convention_financement.exporter'],
    ];

    /** Rapports PDF archivables → type gouverné, libellé. */
    private const PDFS = [
        'synthese' => ['type' => 'export_referentiel_synthese_pdf', 'titre' => 'Synthèse du référentiel financier'],
        'sources' => ['type' => 'export_referentiel_sources_pdf', 'titre' => 'Sources de financement'],
        'ptf' => ['type' => 'export_referentiel_ptf_pdf', 'titre' => 'Partenaires techniques et financiers'],
    ];

    public function __construct(
        private readonly ReferentielFinancierService $referentiel,
        private readonly GouvernanceExportService $gouvernance,
    ) {
    }

    public function sourcesExcel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('source_financement.exporter'), 403);

        return Excel::download(
            new SourcesFinancementExport($this->referentiel->sourcesPourExport()),
            'referentiel-sources-'.now()->format('Ymd').'.xlsx'
        );
    }

    public function partenairesExcel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('partenaire_financier.exporter'), 403);

        return Excel::download(
            new PartenairesFinancierExport($this->referentiel->partenairesPourExport()),
            'referentiel-ptf-'.now()->format('Ymd').'.xlsx'
        );
    }

    public function conventionsExcel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('convention_financement.exporter'), 403);

        $exerciceId = $this->exerciceId($request);

        return Excel::download(
            new ConventionsFinancementExport($this->referentiel->conventionsPourExport($exerciceId)),
            'referentiel-conventions-'.now()->format('Ymd').'.xlsx'
        );
    }

    public function synthesePdf(Request $request): Response
    {
        abort_unless($this->peutGenererPdf($request), 403);

        $suffix = $this->exercice($request)?->annee ?? 'global';

        return $this->telecharger($this->renduSynthesePdf($request), 'referentiel-financier-'.$suffix.'.pdf');
    }

    public function sourcesPdf(Request $request): Response
    {
        abort_unless($this->peutGenererPdf($request), 403);

        return $this->telecharger($this->renduSourcesPdf(), 'sources-financement-'.now()->format('Ymd').'.pdf');
    }

    public function ptfPdf(Request $request): Response
    {
        abort_unless($this->peutGenererPdf($request), 403);

        return $this->telecharger($this->renduPtfPdf($request), 'partenaires-ptf-'.now()->format('Ymd').'.pdf');
    }

    /**
     * Archive un export Excel du référentiel comme rapport officiel gouverné, sans altérer l'export
     * rapide. $cle ∈ {sources, partenaires, conventions}.
     */
    public function archiverExcel(Request $request, string $cle): RedirectResponse
    {
        abort_unless(isset(self::EXCELS[$cle]), 404);
        abort_unless($request->user()->can(self::EXCELS[$cle]['perm']), 403);

        [$contenu, $exercice] = match ($cle) {
            'sources' => [Excel::raw(new SourcesFinancementExport($this->referentiel->sourcesPourExport()), ExcelFormat::XLSX), null],
            'partenaires' => [Excel::raw(new PartenairesFinancierExport($this->referentiel->partenairesPourExport()), ExcelFormat::XLSX), null],
            'conventions' => [Excel::raw(new ConventionsFinancementExport($this->referentiel->conventionsPourExport($this->exerciceId($request))), ExcelFormat::XLSX), $this->exercice($request)],
        };

        return $this->journaliser($cle, self::EXCELS[$cle], $exercice, 'excel', $contenu, $request);
    }

    /**
     * Archive un rapport PDF du référentiel comme rapport officiel gouverné, sans altérer l'export
     * rapide. $cle ∈ {synthese, sources, ptf}.
     */
    public function archiverPdf(Request $request, string $cle): RedirectResponse
    {
        abort_unless(isset(self::PDFS[$cle]), 404);
        abort_unless($this->peutGenererPdf($request), 403);

        [$contenu, $exercice] = match ($cle) {
            'synthese' => [$this->renduSynthesePdf($request), $this->exercice($request)],
            'sources' => [$this->renduSourcesPdf(), null],
            'ptf' => [$this->renduPtfPdf($request), $this->exercice($request)],
        };

        return $this->journaliser($cle, self::PDFS[$cle], $exercice, 'pdf', $contenu, $request);
    }

    /**
     * @param  array{type: string, titre: string}  $meta
     */
    private function journaliser(string $cle, array $meta, ?Exercice $exercice, string $format, string $contenu, Request $request): RedirectResponse
    {
        $rapport = $this->gouvernance->archiver(
            $meta['type'],
            $meta['titre'].($exercice ? ' — '.$exercice->annee : ''),
            $exercice,
            $format,
            $contenu,
            $request->user(),
        );

        return back()->with(
            'succes',
            "Export archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    private function renduSynthesePdf(Request $request): string
    {
        $exercice = $this->exercice($request);

        return Pdf::loadView('referentiel.pdf.synthese', [
            'exercice' => $exercice,
            'stats' => $this->referentiel->indicateurs($exercice?->id),
            'sources' => $this->referentiel->sourcesPourExport()->take(40),
            'partenaires' => $this->referentiel->partenairesPourExport()->take(25),
        ])->setPaper('a4', 'portrait')->output();
    }

    private function renduSourcesPdf(): string
    {
        return Pdf::loadView('referentiel.pdf.sources', [
            'sources' => $this->referentiel->sourcesPourExport(),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function renduPtfPdf(Request $request): string
    {
        return Pdf::loadView('referentiel.pdf.ptf', [
            'exercice' => $this->exercice($request),
            'partenaires' => $this->referentiel->partenairesPourExport(),
            'conventions' => $this->referentiel->conventionsPourExport($this->exerciceId($request)),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function telecharger(string $contenu, string $nom): Response
    {
        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }

    private function exercice(Request $request): ?Exercice
    {
        return Exercice::find($this->exerciceId($request)) ?? ExerciceService::courant();
    }

    private function exerciceId(Request $request): ?int
    {
        return $request->filled('exercice')
            ? (int) Exercice::find($request->integer('exercice'))?->id
            : ExerciceService::courant()?->id;
    }

    private function peutGenererPdf(Request $request): bool
    {
        $u = $request->user();

        return $u->can('nomenclature.generer_pdf')
            || $u->can('source_financement.exporter')
            || $u->can('partenaire_financier.exporter');
    }
}
