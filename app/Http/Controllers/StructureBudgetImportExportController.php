<?php

namespace App\Http\Controllers;

use App\Authorization\StructureBudget;
use App\Exports\ModeleStructureBudgetExport;
use App\Exports\StructureBudgetExport;
use App\Models\Exercice;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\StructureBudgetImportService;
use App\Services\Budget\StructureBudgetService;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StructureBudgetImportExportController extends Controller
{
    public function __construct(
        private StructureBudgetService $service,
        private StructureBudgetImportService $importService,
        private ExerciceService $exerciceService,
    ) {
    }

    /** Hub import / export du module structure budgétaire. */
    public function hub(Request $request): View
    {
        $this->authorize('viewAny', StructureBudget::class);
        [$exercices, $exercice] = $this->exercices($request);

        return view('structure-budget.import-export', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'synthese' => $exercice ? $this->service->synthese($exercice->id) : [],
            'peutImporter' => $request->user()->can('structure_budget.importer') || $request->user()->can('structure_budget.creer'),
            'peutExporter' => $request->user()->can('structure_budget.exporter'),
        ]);
    }

    /** Export consolidé : xlsx (défaut), csv ou pdf. */
    public function export(Request $request): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('export', StructureBudget::class);
        [, $exercice] = $this->exercices($request);

        if (! $exercice) {
            abort(422, 'Sélectionnez un exercice.');
        }

        $format = strtolower((string) $request->input('format', 'xlsx'));
        $filtres = $request->only(['q', 'structure', 'pilier', 'rubrique', 'source', 'projet']);
        $base = sprintf('structure-budget-%d', $exercice->annee);
        $export = new StructureBudgetExport($exercice->id, $this->service, $filtres);

        AuditLogger::enregistrer('structure_budget_export', null, null, [
            'exercice_id' => $exercice->id, 'format' => $format, 'filtres' => $filtres,
        ]);

        return match ($format) {
            'csv' => Excel::download($export, "{$base}.csv", ExcelFormat::CSV),
            'pdf' => $this->exportPdf($exercice, $filtres),
            default => Excel::download($export, "{$base}.xlsx"),
        };
    }

    public function modele(): BinaryFileResponse
    {
        $this->authorize('import', StructureBudget::class);

        return Excel::download(new ModeleStructureBudgetExport, 'modele_structure_budget.xlsx');
    }

    public function previsualiserImport(Request $request): View|RedirectResponse
    {
        $this->authorize('import', StructureBudget::class);
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'exercice_id' => ['required', 'exists:exercices,id'],
        ]);

        $exerciceId = (int) $request->exercice_id;

        try {
            $this->exerciceService->assertBudgetModifiable($exerciceId);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $chemin = $request->file('fichier')->store('imports-temp');
        $apercu = $this->importService->previsualiser(Storage::path($chemin), $exerciceId);

        $request->session()->put('structure_budget_import_path', $chemin);
        $request->session()->put('structure_budget_import_exercice', $exerciceId);
        $request->session()->put('structure_budget_import_nom', $request->file('fichier')->getClientOriginalName());

        return view('structure-budget.import-apercu', array_merge($apercu, [
            'fichier' => $request->file('fichier')->getClientOriginalName(),
            'exercice' => Exercice::find($exerciceId),
        ]));
    }

    public function confirmerImport(Request $request): RedirectResponse
    {
        $this->authorize('import', StructureBudget::class);

        $chemin = $request->session()->pull('structure_budget_import_path');
        $exerciceId = (int) $request->session()->pull('structure_budget_import_exercice');
        $request->session()->forget('structure_budget_import_nom');

        if (! $chemin || ! $exerciceId || ! Storage::exists($chemin)) {
            return redirect()->route('structure-budget.import-export')
                ->with('erreur', 'Aucun fichier en attente. Recommencez l\'import.');
        }

        try {
            $this->exerciceService->assertBudgetModifiable($exerciceId);
        } catch (RuntimeException $e) {
            $this->importService->supprimerFichierTemporaire($chemin);

            return redirect()->route('structure-budget.import-export', ['exercice' => $exerciceId])
                ->with('erreur', $e->getMessage());
        }

        $import = $this->importService->executer(Storage::path($chemin), $exerciceId);
        $this->importService->supprimerFichierTemporaire($chemin);

        AuditLogger::enregistrer('structure_budget_import', null, null, [
            'exercice_id' => $exerciceId,
            'importees' => $import->importees,
            'rejets' => count($import->rejets),
        ]);

        $msg = "Import structure budgétaire : {$import->importees} ligne(s) enregistrée(s).";
        if ($import->rejets) {
            $msg .= ' '.count($import->rejets).' rejetée(s).';
        }

        return redirect()
            ->route('structure-budget.import-export', ['exercice' => $exerciceId])
            ->with('succes', $msg)
            ->with('rapport_import', [
                'importees' => $import->importees,
                'rejets' => array_slice($import->rejets, 0, 50),
                'rejets_total' => count($import->rejets),
            ]);
    }

    public function annulerImport(Request $request): RedirectResponse
    {
        $chemin = $request->session()->pull('structure_budget_import_path');
        $exerciceId = $request->session()->pull('structure_budget_import_exercice');
        $request->session()->forget('structure_budget_import_nom');
        $this->importService->supprimerFichierTemporaire($chemin);

        return redirect()->route('structure-budget.import-export', ['exercice' => $exerciceId]);
    }

    private function exportPdf(Exercice $exercice, array $filtres): StreamedResponse
    {
        $lignes = $this->service->rechercher($exercice->id, $filtres, sansPagination: true);
        $pdf = Pdf::loadView('structure-budget.pdf.consolide', [
            'exercice' => $exercice,
            'synthese' => $this->service->synthese($exercice->id),
            'parDepartement' => $this->service->totauxParType($exercice->id, 'departement'),
            'parDirection' => $this->service->totauxParType($exercice->id, 'direction')->take(15),
            'lignes' => collect($lignes)->take(300),
            'nbLignesTotal' => count($lignes),
        ])->setPaper('a4', 'landscape');

        $nom = sprintf('structure-budget-%d.pdf', $exercice->annee);

        return response()->streamDownload(fn () => print ($pdf->output()), $nom, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function exercices(Request $request): array
    {
        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? $exercices->firstWhere('statut', 'ouvert')
            ?? $exercices->first();

        return [$exercices, $exercice];
    }
}
