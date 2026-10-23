<?php

namespace App\Http\Controllers\Ged;

use App\Exports\Ged\GedArchivesExport;
use App\Exports\Ged\GedDocumentsExport;
use App\Exports\Ged\GedJournalExport;
use App\Exports\Ged\GedSignaturesExport;
use App\Http\Controllers\Controller;
use App\Models\Ged\GedDocument;
use App\Services\Ged\GedSignatureService;
use App\Services\Ged\GedStorageService;
use App\Services\Reporting\GouvernanceExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class GedExportController extends Controller
{
    /** Registres GED archivables → type gouverné, libellé, permission requise. */
    private const REGISTRES = [
        'documents' => ['type' => 'export_ged_registre_documents', 'titre' => 'Registre des documents GED', 'perm' => 'ged.exporter'],
        'archives' => ['type' => 'export_ged_registre_archives', 'titre' => 'Registre des archives GED', 'perm' => 'ged.exporter'],
        'signatures' => ['type' => 'export_ged_registre_signatures', 'titre' => 'Registre des signatures GED', 'perm' => 'ged.exporter'],
        'journal' => ['type' => 'export_ged_journal', 'titre' => 'Journal documentaire GED', 'perm' => 'ged.consulter_journal'],
    ];

    public function __construct(
        private GedStorageService $storage,
        private GedSignatureService $signatures,
        private GouvernanceExportService $gouvernance,
    ) {}

    public function registreDocuments(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('ged.exporter'), 403);

        return Excel::download(new GedDocumentsExport($request->all()), 'registre-documents-'.now()->format('Ymd').'.xlsx');
    }

    public function registreArchives(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('ged.exporter'), 403);

        return Excel::download(new GedArchivesExport, 'registre-archives-'.now()->format('Ymd').'.xlsx');
    }

    public function registreSignatures(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('ged.exporter'), 403);

        return Excel::download(new GedSignaturesExport, 'registre-signatures-'.now()->format('Ymd').'.xlsx');
    }

    public function registreJournal(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('ged.consulter_journal'), 403);

        return Excel::download(new GedJournalExport, 'journal-documentaire-'.now()->format('Ymd').'.xlsx');
    }

    /**
     * Archive un registre GED comme rapport officiel gouverné (versioning, hash, QR, audit), sans
     * altérer l'export rapide. $registre ∈ {documents, archives, signatures, journal}.
     *
     * NB : indexerGed=false — un registre du module GED ne doit pas être réinjecté dans la GED
     * (boucle conceptuelle). Il reste tracé via le registre des rapports et le journal d'audit.
     */
    public function archiver(Request $request, string $registre): RedirectResponse
    {
        abort_unless(isset(self::REGISTRES[$registre]), 404);
        abort_unless($request->user()->can(self::REGISTRES[$registre]['perm']), 403);

        $instance = match ($registre) {
            'documents' => new GedDocumentsExport($request->all()),
            'archives' => new GedArchivesExport,
            'signatures' => new GedSignaturesExport,
            'journal' => new GedJournalExport,
        };

        $rapport = $this->gouvernance->archiver(
            self::REGISTRES[$registre]['type'],
            self::REGISTRES[$registre]['titre'],
            null,
            'excel',
            Excel::raw($instance, ExcelFormat::XLSX),
            $request->user(),
            [],
            indexerGed: false,
        );

        return back()->with(
            'succes',
            "Registre archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    public function ficheDocument(GedDocument $document): Response
    {
        $this->authorize('view', $document);

        $pdf = Pdf::loadView('ged.pdf.fiche', [
            'document' => $document->load(['categorie', 'typeDocument', 'createur', 'versions']),
            'verification' => $this->signatures->verifier($document),
        ]);

        return $pdf->download($this->nomPdf('fiche', $document->reference, $document->id));
    }

    public function certificatIntegrite(GedDocument $document): Response
    {
        $this->authorize('view', $document);

        $pdf = Pdf::loadView('ged.pdf.certificat_integrite', [
            'document' => $document,
            'integrite' => $this->storage->verifierIntegrite($document->chemin_fichier, $document->hash_sha256),
        ]);

        return $pdf->download($this->nomPdf('certificat-integrite', $document->uuid, $document->id));
    }

    public function certificatSignature(GedDocument $document): Response
    {
        $this->authorize('view', $document);

        $pdf = Pdf::loadView('ged.pdf.certificat_signature', [
            'document' => $document->load('signatures.signataire'),
            'verification' => $this->signatures->verifier($document),
        ]);

        return $pdf->download($this->nomPdf('certificat-signature', $document->uuid, $document->id));
    }

    public function bordereauDepot(GedDocument $document): Response
    {
        $this->authorize('view', $document);

        return Pdf::loadView('ged.pdf.bordereau_depot', ['document' => $document])
            ->download($this->nomPdf('bordereau-depot', $document->reference, $document->id));
    }

    public function bordereauArchivage(GedDocument $document): Response
    {
        $this->authorize('view', $document);

        $document->load(['archives.archiveur']);

        return Pdf::loadView('ged.pdf.bordereau_archivage', ['document' => $document])
            ->download($this->nomPdf('bordereau-archivage', $document->reference, $document->id));
    }

    private function nomPdf(string $prefixe, ?string $identifiant, int|string $fallback): string
    {
        $base = Str::of($identifiant ?: (string) $fallback)
            ->ascii()
            ->replaceMatches('/[\\\\\/]+/', '-')
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-._')
            ->lower()
            ->toString();

        return $prefixe.'-'.($base !== '' ? $base : $fallback).'.pdf';
    }
}
