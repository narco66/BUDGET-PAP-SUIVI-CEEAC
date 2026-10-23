<?php

namespace App\Http\Controllers;

use App\Exports\Institution\StructuresImportTemplateExport;
use App\Imports\StructuresImport;
use App\Models\Structure;
use App\Services\Institution\InstitutionImportService;
use App\Support\AuditLogger;
use App\Support\ReferentielAdministratifAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Import Excel du référentiel institutionnel (phase A8). */
class InstitutionImportController extends Controller
{
    public function __construct(private readonly InstitutionImportService $imports)
    {
    }

    public function modeleStructures(Request $request): BinaryFileResponse
    {
        $this->autoriserImport($request);

        return Excel::download(
            new StructuresImportTemplateExport($this->imports->entetesModele()),
            'modele-import-structures.xlsx'
        );
    }

    public function importerStructures(Request $request): RedirectResponse
    {
        $this->autoriserImport($request);

        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $import = new StructuresImport($this->imports);
        Excel::import($import, $request->file('fichier'));

        $stats = $import->stats;
        AuditLogger::enregistrer('import_structures', null, null, $stats);

        $msg = "Import terminé : {$stats['crees']} créée(s), {$stats['mis_a_jour']} mise(s) à jour.";
        if ($stats['erreurs'] !== []) {
            $msg .= ' '.count($stats['erreurs']).' ligne(s) en erreur.';
        }

        return back()->with($stats['erreurs'] === [] ? 'succes' : 'avertissement', $msg);
    }

    private function autoriserImport(Request $request): void
    {
        abort_unless(ReferentielAdministratifAuthorization::peut($request->user(), 'importer'), 403);
        $this->authorize('viewAny', Structure::class);
    }
}
