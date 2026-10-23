<?php

namespace App\Http\Controllers;

use App\Exports\Recettes\ArrieresExport;
use App\Exports\Recettes\EncaissementsExport;
use App\Exports\Recettes\MobilisationEtatsExport;
use App\Exports\Recettes\PrevisionsRecettesExport;
use App\Models\Exercice;
use App\Services\Recettes\GestionRecettesService;
use App\Services\Recettes\RecettesReportingService;
use App\Services\Reporting\GouvernanceExportService;
use App\Services\Reporting\WordRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Phase R5 — états et rapports du module Recettes (PDF / Excel / CSV / Word) + archivage gouverné (P0). */
class RecettesRapportController extends Controller
{
    /**
     * Catalogue des jeux archivables : clé d'URL → identifiant Rapport gouverné, libellé, formats permis.
     *
     * @var array<string, array{type: string, titre: string, formats: list<string>}>
     */
    private const ARCHIVES = [
        'mobilisation' => ['type' => 'export_recettes_mobilisation', 'titre' => 'Mobilisation des contributions par État membre', 'formats' => ['pdf', 'excel', 'csv']],
        'arrieres' => ['type' => 'export_recettes_arrieres', 'titre' => 'Arriérés de contributions', 'formats' => ['pdf', 'excel']],
        'encaissements' => ['type' => 'export_recettes_encaissements', 'titre' => 'Journal des encaissements', 'formats' => ['excel', 'csv']],
        'previsions' => ['type' => 'export_recettes_previsions', 'titre' => 'Prévisions de recettes', 'formats' => ['excel', 'csv']],
        'rapport-financier' => ['type' => 'export_recettes_financier', 'titre' => 'Rapport financier des recettes', 'formats' => ['pdf', 'word']],
    ];

    public function __construct(
        private readonly RecettesReportingService $reporting,
        private readonly GestionRecettesService $gestion,
        private readonly GouvernanceExportService $gouvernance,
        private readonly WordRenderer $word,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('recette.voir'), 403);

        return view('recettes.rapports.index', [
            'exercice' => $this->exercice($request),
            'exercices' => Exercice::orderByDesc('annee')->get(),
        ]);
    }

    public function mobilisationPdf(Request $request): Response
    {
        $this->autoriser($request);
        $exercice = $this->exercice($request);
        $eid = $exercice?->id;

        $pdf = Pdf::loadView('recettes.pdf.mobilisation', [
            'exercice' => $exercice,
            'rows' => $this->reporting->mobilisationParEtat($eid),
            'synthese' => $this->reporting->syntheseMobilisation($eid),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('mobilisation-contributions-'.($exercice?->annee ?? 'global').'.pdf');
    }

    public function mobilisationExcel(Request $request): BinaryFileResponse
    {
        $this->autoriser($request);
        $rows = $this->reporting->mobilisationParEtat($this->exercice($request)?->id);

        return Excel::download(new MobilisationEtatsExport($rows), 'mobilisation-contributions-'.now()->format('Ymd').'.xlsx');
    }

    public function mobilisationCsv(Request $request): BinaryFileResponse
    {
        $this->autoriser($request);
        $rows = $this->reporting->mobilisationParEtat($this->exercice($request)?->id);

        return Excel::download(new MobilisationEtatsExport($rows), 'mobilisation-contributions-'.now()->format('Ymd').'.csv', ExcelFormat::CSV);
    }

    public function arrieresPdf(Request $request): Response
    {
        $this->autoriser($request);
        $exercice = $this->exercice($request);

        $pdf = Pdf::loadView('recettes.pdf.arrieres', [
            'exercice' => $exercice,
            'appels' => $this->reporting->arrieres($exercice?->id),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('arrieres-contributions-'.($exercice?->annee ?? 'global').'.pdf');
    }

    public function arrieresExcel(Request $request): BinaryFileResponse
    {
        $this->autoriser($request);

        return Excel::download(
            new ArrieresExport($this->reporting->arrieres($this->exercice($request)?->id)),
            'arrieres-contributions-'.now()->format('Ymd').'.xlsx'
        );
    }

    public function encaissementsExcel(Request $request): BinaryFileResponse
    {
        $this->autoriser($request);

        return Excel::download(
            new EncaissementsExport($this->reporting->encaissements($this->exercice($request)?->id)),
            'encaissements-'.now()->format('Ymd').'.xlsx'
        );
    }

    public function encaissementsCsv(Request $request): BinaryFileResponse
    {
        $this->autoriser($request);

        return Excel::download(
            new EncaissementsExport($this->reporting->encaissements($this->exercice($request)?->id)),
            'encaissements-'.now()->format('Ymd').'.csv',
            ExcelFormat::CSV
        );
    }

    public function previsionsExcel(Request $request): BinaryFileResponse
    {
        $this->autoriser($request);

        return Excel::download(
            new PrevisionsRecettesExport($this->reporting->previsions($this->exercice($request)?->id)),
            'previsions-recettes-'.now()->format('Ymd').'.xlsx'
        );
    }

    public function previsionsCsv(Request $request): BinaryFileResponse
    {
        $this->autoriser($request);

        return Excel::download(
            new PrevisionsRecettesExport($this->reporting->previsions($this->exercice($request)?->id)),
            'previsions-recettes-'.now()->format('Ymd').'.csv',
            ExcelFormat::CSV
        );
    }

    /** Rapport financier consolidé des recettes (PDF). */
    public function rapportFinancierPdf(Request $request): Response
    {
        $this->autoriser($request);
        $exercice = $this->exercice($request);
        $nom = 'rapport-financier-recettes-'.($exercice?->annee ?? 'global').'.pdf';

        return response($this->pdfRapportFinancier($exercice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }

    /** Rapport financier consolidé des recettes (Word .docx). */
    public function rapportFinancierWord(Request $request): Response
    {
        $this->autoriser($request);
        $exercice = $this->exercice($request);

        $binaire = $this->word->rendre(
            $this->composeRapportFinancier($exercice),
            $this->metaRapportFinancier($exercice),
            null,
        );
        $nom = 'rapport-financier-recettes-'.($exercice?->annee ?? 'global').'.docx';

        return response($binaire, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }

    /**
     * Archive un état recettes comme rapport officiel gouverné (versioning, hash, QR, GED, audit),
     * sans altérer l'export rapide. Action opt-in déclenchée explicitement par l'utilisateur.
     */
    public function archiver(Request $request, string $cle, string $format): RedirectResponse
    {
        abort_unless(isset(self::ARCHIVES[$cle]), 404);
        abort_unless(in_array($format, self::ARCHIVES[$cle]['formats'], true), 404);
        abort_unless($request->user()->can('recette.exporter'), 403);

        $exercice = $this->exercice($request);

        $rapport = $this->gouvernance->archiver(
            self::ARCHIVES[$cle]['type'],
            self::ARCHIVES[$cle]['titre'],
            $exercice,
            $format,
            $this->rendre($cle, $format, $exercice),
            $request->user(),
            array_filter(['exercice' => $exercice?->annee]),
        );

        return back()->with(
            'succes',
            "Export archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    /** Produit le binaire d'un jeu d'export pour un format donné (source unique des rendus archivés). */
    private function rendre(string $cle, string $format, ?Exercice $exercice): string
    {
        $eid = $exercice?->id;

        return match ("{$cle}:{$format}") {
            'mobilisation:pdf' => Pdf::loadView('recettes.pdf.mobilisation', [
                'exercice' => $exercice,
                'rows' => $this->reporting->mobilisationParEtat($eid),
                'synthese' => $this->reporting->syntheseMobilisation($eid),
            ])->setPaper('a4', 'portrait')->output(),
            'mobilisation:excel' => Excel::raw(new MobilisationEtatsExport($this->reporting->mobilisationParEtat($eid)), ExcelFormat::XLSX),
            'mobilisation:csv' => Excel::raw(new MobilisationEtatsExport($this->reporting->mobilisationParEtat($eid)), ExcelFormat::CSV),
            'arrieres:pdf' => Pdf::loadView('recettes.pdf.arrieres', [
                'exercice' => $exercice,
                'appels' => $this->reporting->arrieres($eid),
            ])->setPaper('a4', 'landscape')->output(),
            'arrieres:excel' => Excel::raw(new ArrieresExport($this->reporting->arrieres($eid)), ExcelFormat::XLSX),
            'encaissements:excel' => Excel::raw(new EncaissementsExport($this->reporting->encaissements($eid)), ExcelFormat::XLSX),
            'encaissements:csv' => Excel::raw(new EncaissementsExport($this->reporting->encaissements($eid)), ExcelFormat::CSV),
            'previsions:excel' => Excel::raw(new PrevisionsRecettesExport($this->reporting->previsions($eid)), ExcelFormat::XLSX),
            'previsions:csv' => Excel::raw(new PrevisionsRecettesExport($this->reporting->previsions($eid)), ExcelFormat::CSV),
            'rapport-financier:pdf' => $this->pdfRapportFinancier($exercice),
            'rapport-financier:word' => $this->word->rendre($this->composeRapportFinancier($exercice), $this->metaRapportFinancier($exercice), null),
        };
    }

    private function pdfRapportFinancier(?Exercice $exercice): string
    {
        $data = $this->donneesRapport($exercice?->id);

        return Pdf::loadView('recettes.pdf.rapport_financier', [
            'exercice' => $exercice,
            'synthesePrev' => $data['synthesePrev'],
            'mobilisation' => $data['mobilisation'],
            'synthMob' => $data['synthMob'],
            'arrieres' => $data['arrieres'],
        ])->setPaper('a4', 'portrait')->output();
    }

    /** @return array<string, mixed> Sections normalisées du rapport financier (source unique PDF/Word). */
    private function composeRapportFinancier(?Exercice $exercice): array
    {
        $data = $this->donneesRapport($exercice?->id);

        return [
            'titre' => 'Rapport financier des recettes',
            'sous_titre' => 'Exercice '.($exercice?->annee ?? 'global').' — Mobilisation des ressources de la Commission',
            'sections' => [
                [
                    'type' => 'kpi', 'titre' => 'Synthèse des recettes',
                    'items' => [
                        ['label' => 'Recettes prévues (XAF)', 'valeur' => $data['synthesePrev']['montant_prevu'], 'fmt' => 'money'],
                        ['label' => 'Recettes réalisées (XAF)', 'valeur' => $data['synthesePrev']['montant_realise'], 'fmt' => 'money'],
                        ['label' => 'Contributions appelées (XAF)', 'valeur' => $data['synthMob']['appele'], 'fmt' => 'money'],
                        ['label' => 'Contributions recouvrées (XAF)', 'valeur' => $data['synthMob']['recouvre'], 'fmt' => 'money'],
                        ['label' => 'Arriérés (XAF)', 'valeur' => $data['synthMob']['arriere'], 'fmt' => 'money'],
                        ['label' => 'Taux de recouvrement', 'valeur' => $data['synthMob']['taux'], 'fmt' => 'percent'],
                    ],
                ],
                [
                    'type' => 'table', 'titre' => 'Mobilisation par État membre',
                    'colonnes' => [
                        ['label' => 'État', 'fmt' => 'text'], ['label' => 'Appelé', 'fmt' => 'money'],
                        ['label' => 'Recouvré', 'fmt' => 'money'], ['label' => 'Arriéré', 'fmt' => 'money'],
                        ['label' => 'Taux', 'fmt' => 'percent'],
                    ],
                    'lignes' => $data['mobilisation']->map(fn ($r) => [$r->pays, $r->appele, $r->recouvre, $r->arriere, $r->taux])->all(),
                    'total' => ['TOTAL', $data['synthMob']['appele'], $data['synthMob']['recouvre'], $data['synthMob']['arriere'], $data['synthMob']['taux']],
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    private function metaRapportFinancier(?Exercice $exercice): array
    {
        return [
            'reference' => 'RAP-REC-'.($exercice?->annee ?? 'GLOBAL'),
            'version' => '1',
            'date' => now()->format('d/m/Y H:i'),
            'verification' => 'Document interne CEEAC',
        ];
    }

    /** @return array<string, mixed> */
    private function donneesRapport(?int $exerciceId): array
    {
        return [
            'synthesePrev' => $this->gestion->syntheseExercice($exerciceId ?? 0),
            'mobilisation' => $this->reporting->mobilisationParEtat($exerciceId),
            'synthMob' => $this->reporting->syntheseMobilisation($exerciceId),
            'arrieres' => $this->reporting->arrieres($exerciceId),
        ];
    }

    private function autoriser(Request $request): void
    {
        abort_unless($request->user()->can('recette.exporter'), 403);
    }

    private function exercice(Request $request): ?Exercice
    {
        if ($request->filled('exercice')) {
            return Exercice::find($request->exercice);
        }

        return Exercice::actif()->first() ?? Exercice::orderByDesc('annee')->first();
    }
}
