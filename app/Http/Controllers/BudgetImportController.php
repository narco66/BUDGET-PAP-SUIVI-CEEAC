<?php

namespace App\Http\Controllers;

use App\Exports\LignesBudgetairesExport;
use App\Exports\ModeleLignesExport;
use App\Exports\ModeleNomenclatureExport;
use App\Exports\ModelePapExport;
use App\Imports\LignesBudgetairesImport;
use App\Imports\NomenclatureImport;
use App\Imports\PapImport;
use App\Models\Exercice;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\PapBudgetRapprochementService;
use App\Services\Pap\PapReferentielService;
use App\Support\AuditLogger;
use RuntimeException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BudgetImportController extends Controller
{
    public function form(Request $request): View
    {
        abort_unless($request->user()->can('ligne_budgetaire.voir'), 403);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? $exercices->firstWhere('statut', 'ouvert')
            ?? $exercices->first();
        $rapprochement = $exercice
            ? app(PapBudgetRapprochementService::class)->analyser($exercice->id)
            : null;

        return view('budget.import', compact('exercices', 'exercice', 'rapprochement'));
    }

    public function importerNomenclature(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomenclature.creer'), 403);
        $request->validate(['fichier' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $import = new NomenclatureImport;
        Excel::import($import, $request->file('fichier'));

        return back()->with('succes', $this->messageImport($import))->with('rapport_nomenclature', $this->rapportImport($import));
    }

    /**
     * Étape 1 de l'assistant : prévisualisation sans persistance. Le fichier est stocké en zone
     * temporaire (chemin conservé en session, non exposé à l'utilisateur), analysé (colonnes
     * détectées, premières lignes, lignes valides/rejetées) puis confirmé à l'étape 2.
     */
    public function previsualiserNomenclature(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->can('nomenclature.creer'), 403);
        $request->validate(['fichier' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $chemin = $request->file('fichier')->store('imports-temp');
        $rows = (Excel::toCollection(new NomenclatureImport, Storage::path($chemin))->first() ?? collect())
            ->map(fn ($r) => is_array($r) ? $r : $r->toArray());

        $colonnesDetectees = $rows->isNotEmpty() ? array_keys($rows->first()) : [];
        $colonnesManquantes = array_values(array_diff(
            ['sens', 'titre_code', 'article_code', 'paragraphe_code', 'rubrique_code'],
            $colonnesDetectees
        ));

        $valides = 0;
        $rejets = [];
        foreach ($rows as $i => $row) {
            $motif = NomenclatureImport::motifInvalidite((array) $row);
            if ($motif === null) {
                $valides++;
            } elseif (count($rejets) < 30) {
                $rejets[] = ['ligne' => $i + 2, 'motif' => $motif];
            }
        }

        $request->session()->put('import_nomenclature_path', $chemin);
        $request->session()->put('import_nomenclature_nom', $request->file('fichier')->getClientOriginalName());

        return view('budget.import-apercu', [
            'fichier' => $request->file('fichier')->getClientOriginalName(),
            'total' => $rows->count(),
            'valides' => $valides,
            'rejets' => $rejets,
            'colonnesDetectees' => $colonnesDetectees,
            'colonnesManquantes' => $colonnesManquantes,
            'apercu' => $rows->take(10),
        ]);
    }

    /** Étape 2 de l'assistant : import effectif du fichier prévisualisé puis nettoyage. */
    public function confirmerNomenclature(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomenclature.creer'), 403);

        $chemin = $request->session()->pull('import_nomenclature_path');
        $request->session()->forget('import_nomenclature_nom');
        if (! $chemin || ! Storage::exists($chemin)) {
            return redirect()->route('budget.import.form')->with('erreur', 'Aucun fichier prévisualisé à confirmer. Recommencez l\'import.');
        }

        $import = new NomenclatureImport;
        Excel::import($import, Storage::path($chemin));
        Storage::delete($chemin);

        return redirect()->route('budget.import.form')
            ->with('succes', $this->messageImport($import))
            ->with('rapport_nomenclature', $this->rapportImport($import));
    }

    /** Abandonne l'aperçu en cours et supprime le fichier temporaire. */
    public function annulerApercuNomenclature(Request $request): RedirectResponse
    {
        $chemin = $request->session()->pull('import_nomenclature_path');
        $request->session()->forget('import_nomenclature_nom');
        if ($chemin && Storage::exists($chemin)) {
            Storage::delete($chemin);
        }

        return redirect()->route('budget.import.form');
    }

    private function messageImport(NomenclatureImport $import): string
    {
        AuditLogger::enregistrer('import_nomenclature', null, null, [
            'crees' => $import->crees, 'majs' => $import->majs, 'rejets' => count($import->rejets),
        ]);

        return "Nomenclature importée : {$import->crees} créée(s), {$import->majs} mise(s) à jour"
            .(count($import->rejets) ? ', '.count($import->rejets).' rejetée(s)' : '').'.';
    }

    private function rapportImport(NomenclatureImport $import): array
    {
        return [
            'crees' => $import->crees,
            'majs' => $import->majs,
            'total' => $import->lignesImportees,
            'rejets' => array_slice($import->rejets, 0, 50),
            'rejets_total' => count($import->rejets),
        ];
    }

    public function importerLignes(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ligne_budgetaire.creer'), 403);
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'exercice_id' => ['required', 'exists:exercices,id'],
        ]);

        $exerciceId = (int) $request->exercice_id;

        try {
            app(ExerciceService::class)->assertBudgetModifiable($exerciceId);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $import = new LignesBudgetairesImport($exerciceId);
        Excel::import($import, $request->file('fichier'));
        AuditLogger::enregistrer('import_lignes', null, null, ['importees' => $import->importees, 'rejets' => count($import->rejets)]);

        $msg = "Lignes importées : {$import->importees}.";
        if ($import->rejets) {
            return back()->with('succes', $msg)->with('rejets', $import->rejets);
        }

        return back()->with('succes', $msg);
    }

    public function importerPap(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rbm.creer'), 403);
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'exercice_id' => ['required', 'exists:exercices,id'],
        ]);

        $exerciceId = (int) $request->exercice_id;

        try {
            app(PapReferentielService::class)->assertExerciceModifiable($exerciceId);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $import = new PapImport($exerciceId);
        Excel::import($import, $request->file('fichier'));
        AuditLogger::enregistrer('import_pap', null, null, [
            'piliers' => $import->piliers, 'axes' => $import->axes, 'produits' => $import->produits,
            'sous_produits' => $import->sousProduits, 'activites' => $import->activites,
            'liaisons_budget' => $import->liaisonsBudget,
        ]);

        $msg = "Détail PAP importé : {$import->activites} activité(s), {$import->produits} produit(s).";
        if ($import->liaisonsBudget) {
            $msg .= " {$import->liaisonsBudget} liaison(s) budget synchronisée(s).";
        }

        $analyse = app(PapBudgetRapprochementService::class)->analyser($exerciceId);
        if ($analyse['ecarts_total'] > 0) {
            $msg .= " {$analyse['ecarts_total']} écart(s) PAP↔budget — lancez le rapprochement.";
        }

        return back()->with('succes', $msg)->with('rapprochement', $analyse);
    }

    public function rapprocherPap(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ligne_budgetaire.modifier') || $request->user()->can('rbm.modifier'), 403);
        $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id'],
            'aligner_montants' => ['nullable', 'boolean'],
        ]);

        $exerciceId = (int) $request->exercice_id;

        try {
            app(ExerciceService::class)->assertBudgetModifiable($exerciceId);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $stats = app(PapBudgetRapprochementService::class)->rapprocher($exerciceId, [
            'lier_codes' => true,
            'aligner_montants' => $request->boolean('aligner_montants'),
        ]);

        $apres = app(PapBudgetRapprochementService::class)->analyser($exerciceId);
        AuditLogger::enregistrer('pap_rapprochement_ui', null, null, array_merge($stats, ['ecarts_restants' => $apres['ecarts_total']]));

        $msg = "Rapprochement effectué : {$stats['liaisons_creees']} liaison(s), {$stats['liaisons_sync']} sync(s).";
        if ($stats['montants_alignes']) {
            $msg .= " {$stats['montants_alignes']} montant(s) aligné(s).";
        }
        if ($apres['ecarts_total'] > 0) {
            $msg .= " {$apres['ecarts_total']} écart(s) restant(s).";
        }

        return redirect()
            ->route('budget.import.form', ['exercice' => $exerciceId])
            ->with('succes', $msg)
            ->with('rapprochement', $apres);
    }

    public function modelePap(): BinaryFileResponse
    {
        return Excel::download(new ModelePapExport, 'modele_detail_pap.xlsx');
    }

    public function modeleNomenclature(): BinaryFileResponse
    {
        return Excel::download(new ModeleNomenclatureExport, 'modele_nomenclature.xlsx');
    }

    public function modeleLignes(): BinaryFileResponse
    {
        return Excel::download(new ModeleLignesExport, 'modele_lignes_budgetaires.xlsx');
    }

    public function exporter(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('ligne_budgetaire.exporter'), 403);
        $exercice = Exercice::findOrFail($request->integer('exercice'));

        return Excel::download(new LignesBudgetairesExport($exercice->id), "budget_{$exercice->annee}.xlsx");
    }
}
