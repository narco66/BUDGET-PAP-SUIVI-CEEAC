<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\CampagneSuivi;
use App\Models\Exercice;
use App\Services\Suivi\SuiviPhysiqueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampagneSuiviController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CampagneSuivi::class);
        $campagnes = CampagneSuivi::with('exercice')->withCount('collectes')->latest()->paginate(20);

        return view('suivi.index', compact('campagnes'));
    }

    public function create(): View
    {
        $this->authorize('create', CampagneSuivi::class);

        return view('suivi.create', ['exercices' => Exercice::orderByDesc('annee')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CampagneSuivi::class);

        $data = $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id'],
            'libelle' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['mensuel', 'trimestriel', 'semestriel', 'annuel', 'pluriannuel'])],
            'periode' => ['required', 'string', 'max:20', Rule::unique('campagnes_suivi')->where('exercice_id', $request->exercice_id)],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $campagne = CampagneSuivi::create($data);

        return redirect()->route('suivi.show', $campagne)->with('succes', 'Campagne créée.');
    }

    public function show(CampagneSuivi $suivi): View
    {
        $this->authorize('view', $suivi);

        // Activités du PAP de l'exercice + leur collecte éventuelle pour cette campagne
        $activites = Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $suivi->exercice_id))
            ->with(['structure', 'collectes' => fn ($q) => $q->where('campagne_id', $suivi->id)])
            ->orderBy('code')->paginate(25);

        return view('suivi.show', [
            'campagne' => $suivi,
            'activites' => $activites,
            'consolidation' => $this->consolidation($suivi->id),
            'realisationsProduits' => $suivi->realisationsProduits()->with('produit')->get(),
            'realisationsSousProduits' => $suivi->realisationsSousProduits()->with('sousProduit')->get(),
            'alertes' => $suivi->alertes()->where('resolu', false)->orderByDesc('detecte_le')->get(),
        ]);
    }

    public function cloturer(CampagneSuivi $suivi, SuiviPhysiqueService $suiviPhysique, \App\Services\Suivi\SuiviEvaluationService $evaluation): RedirectResponse
    {
        abort_unless(request()->user()->can('suivi.valider'), 403);
        abort_unless($suivi->estOuverte(), 422, 'Campagne déjà clôturée.');

        $resultat = $suiviPhysique->cloturerCampagne($suivi);
        $evaluation->genererEvaluation($suivi->exercice_id, $suivi->id, request()->user()->id);

        return back()->with('succes', "Campagne clôturée — {$resultat['produits_consolides']} produit(s), {$resultat['sous_produits_consolides']} sous-produit(s), {$resultat['alertes_creees']} alerte(s). Évaluation S&E générée.");
    }

    public function destroy(CampagneSuivi $suivi): RedirectResponse
    {
        $this->authorize('delete', $suivi);
        $suivi->delete();

        return redirect()->route('suivi.index')->with('succes', 'Campagne supprimée.');
    }

    /** Consolidation des taux validés par structure (EF-D7-05). */
    private function consolidation(int $campagneId)
    {
        return DB::table('collectes as c')
            ->join('activites as a', 'c.activite_id', '=', 'a.id')
            ->leftJoin('structures as s', 'a.structure_id', '=', 's.id')
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('c.campagne_id', $campagneId)->where('c.statut', 'validee')
            ->groupBy('s.id', 's.libelle')
            ->selectRaw('COALESCE(s.libelle, "(non rattachée)") libelle, COUNT(*) n,
                         ROUND(AVG(c.taux_physique),1) phys,
                         ROUND(AVG(CASE
                            WHEN a.ligne_budgetaire_id IS NOT NULL AND a.montant_total > 0
                            THEN (lb.montant_paye / a.montant_total * 100)
                            ELSE c.taux_financier
                         END),1) fin')
            ->orderByDesc('n')->get();
    }
}
