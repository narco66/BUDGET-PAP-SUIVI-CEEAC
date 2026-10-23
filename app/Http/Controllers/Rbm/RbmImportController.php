<?php

namespace App\Http\Controllers\Rbm;

use App\Exports\Rbm\ModeleIndicateursExport;
use App\Exports\Rbm\ModeleRbmChaineExport;
use App\Http\Controllers\Controller;
use App\Imports\IndicateursImport;
use App\Imports\RbmChaineImport;
use App\Models\Exercice;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Import Excel contrôlé de la chaîne RBM/GAR et des indicateurs. */
class RbmImportController extends Controller
{
    public function form(Request $request): View
    {
        abort_unless($request->user()->can('rbm.importer'), 403);

        return view('rbm.import', [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'exercice' => Exercice::find($request->integer('exercice')),
        ]);
    }

    public function importer(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rbm.importer'), 403);

        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'exercice_id' => ['required', 'exists:exercices,id'],
        ]);

        $import = new RbmChaineImport((int) $request->exercice_id);
        Excel::import($import, $request->file('fichier'));

        AuditLogger::enregistrer('import_rbm_chaine', null, null, [
            'exercice_id' => $request->exercice_id,
            'lignes' => $import->lignesTraitees,
            'activites' => $import->activites,
            'taches' => $import->taches,
            'rejets' => count($import->rejets),
        ]);

        $msg = "Import chaîne : {$import->activites} activité(s), {$import->taches} tâche(s).";
        if ($import->rejets) {
            return back()->with('succes', $msg)->with('rejets', $import->rejets);
        }

        return redirect()->route('rbm.arbre', $request->exercice_id)->with('succes', $msg);
    }

    public function importerIndicateurs(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rbm.importer') || $request->user()->can('indicateur.importer'), 403);

        $request->validate([
            'fichier_indicateurs' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'exercice_id_indicateurs' => ['required', 'exists:exercices,id'],
        ]);

        $import = new IndicateursImport((int) $request->exercice_id_indicateurs);
        Excel::import($import, $request->file('fichier_indicateurs'));

        AuditLogger::enregistrer('import_rbm_indicateurs', null, null, [
            'exercice_id' => $request->exercice_id_indicateurs,
            'importes' => $import->importes,
            'rejets' => count($import->rejets),
        ]);

        $msg = "Import indicateurs : {$import->importes} enregistrement(s).";
        if ($import->rejets) {
            return back()->with('succes', $msg)->with('rejets', $import->rejets);
        }

        return back()->with('succes', $msg);
    }

    public function modele(): BinaryFileResponse
    {
        abort_unless(request()->user()?->can('rbm.importer'), 403);

        return Excel::download(new ModeleRbmChaineExport, 'modele_chaine_rbm.xlsx');
    }

    public function modeleIndicateurs(): BinaryFileResponse
    {
        abort_unless(request()->user()?->can('rbm.importer'), 403);

        return Excel::download(new ModeleIndicateursExport, 'modele_indicateurs_rbm.xlsx');
    }
}
