<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Pap;
use App\Models\PapHistorique;
use App\Models\PapMesureCorrective;
use App\Models\PapRisque;
use App\Models\User;
use App\Services\Pap\PapGarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PapGarController extends Controller
{
    public function __construct(private readonly PapGarService $gar)
    {
    }

    public function storeRisque(Request $request, Pap $pap): RedirectResponse
    {
        $this->authorize('gererGar', $pap);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:pap_risques,code,NULL,id,pap_id,'.$pap->id],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activite_id' => ['nullable', 'exists:activites,id'],
            'probabilite' => ['required', 'in:faible,moyenne,elevee'],
            'impact' => ['required', 'in:faible,moyen,eleve,critique'],
            'statut' => ['required', 'in:ouvert,attenue,clos'],
            'mitigation' => ['nullable', 'string'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
        ]);

        try {
            $this->gar->creerRisque($pap, $data);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Risque PAP enregistré.')->withFragment('pap-tab-risques');
    }

    public function updateRisque(Request $request, Pap $pap, PapRisque $risque): RedirectResponse
    {
        $this->authorize('gererGar', $pap);
        abort_unless((int) $risque->pap_id === (int) $pap->id, 404);

        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activite_id' => ['nullable', 'exists:activites,id'],
            'probabilite' => ['required', 'in:faible,moyenne,elevee'],
            'impact' => ['required', 'in:faible,moyen,eleve,critique'],
            'statut' => ['required', 'in:ouvert,attenue,clos'],
            'mitigation' => ['nullable', 'string'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
        ]);

        try {
            $this->gar->mettreAJourRisque($risque, $data);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Risque mis à jour.')->withFragment('pap-tab-risques');
    }

    public function destroyRisque(Pap $pap, PapRisque $risque): RedirectResponse
    {
        $this->authorize('gererGar', $pap);
        abort_unless((int) $risque->pap_id === (int) $pap->id, 404);

        try {
            $this->gar->supprimerRisque($risque);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Risque supprimé.')->withFragment('pap-tab-risques');
    }

    public function storeMesure(Request $request, Pap $pap): RedirectResponse
    {
        $this->authorize('gererGar', $pap);

        $data = $request->validate([
            'pap_risque_id' => ['nullable', 'exists:pap_risques,id'],
            'activite_id' => ['nullable', 'exists:activites,id'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string'],
            'echeance' => ['nullable', 'date'],
            'statut' => ['required', 'in:planifiee,en_cours,realisee,annulee'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
        ]);

        try {
            $this->gar->creerMesure($pap, $data);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Mesure corrective enregistrée.')->withFragment('pap-tab-risques');
    }

    public function updateMesure(Request $request, Pap $pap, PapMesureCorrective $mesure): RedirectResponse
    {
        $this->authorize('gererGar', $pap);
        abort_unless((int) $mesure->pap_id === (int) $pap->id, 404);

        $data = $request->validate([
            'pap_risque_id' => ['nullable', 'exists:pap_risques,id'],
            'activite_id' => ['nullable', 'exists:activites,id'],
            'description' => ['required', 'string'],
            'echeance' => ['nullable', 'date'],
            'statut' => ['required', 'in:planifiee,en_cours,realisee,annulee'],
            'resultat' => ['nullable', 'string'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
        ]);

        try {
            $this->gar->mettreAJourMesure($mesure, $data);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Mesure corrective mise à jour.')->withFragment('pap-tab-risques');
    }

    public function enregistrerEcarts(Request $request, Pap $pap): RedirectResponse
    {
        $this->authorize('gererGar', $pap);

        $data = $request->validate([
            'ecarts' => ['required', 'array'],
            'ecarts.*.cause_ecart' => ['nullable', 'string', 'max:2000'],
            'ecarts.*.mesure_corrective' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $count = $this->gar->enregistrerEcartsActivites($pap, $data['ecarts']);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "{$count} activité(s) mise(s) à jour.")->withFragment('pap-tab-ecarts');
    }

    public function snapshot(Pap $pap, PapHistorique $historique): View
    {
        $this->authorize('view', $pap);
        abort_unless((int) $historique->pap_id === (int) $pap->id, 404);

        return view('paps.snapshot', [
            'pap' => $pap,
            'historique' => $historique->load('auteur'),
        ]);
    }
}
