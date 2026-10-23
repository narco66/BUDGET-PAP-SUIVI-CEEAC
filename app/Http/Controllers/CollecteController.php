<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\Pap;
use App\Services\Suivi\CollecteJustificatifService;
use App\Services\Suivi\SuiviPhysiqueService;
use App\Services\WorkflowService;
use App\Support\AuditLogger;
use App\Support\WorkflowRejetCategorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CollecteController extends Controller
{
    public function __construct(
        private readonly CollecteJustificatifService $justificatifs,
        private readonly SuiviPhysiqueService $suiviPhysique,
        private readonly WorkflowService $workflow,
    ) {
    }

    /** Saisie / mise à jour d'une collecte pour une activité d'une campagne (EF-D7-03). */
    public function enregistrer(Request $request, CampagneSuivi $suivi, Activite $activite): RedirectResponse
    {
        abort_unless($suivi->estOuverte(), 422, 'Campagne clôturée.');

        abort_unless(
            Activite::whereKey($activite->id)
                ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $suivi->exercice_id))
                ->exists(),
            422,
            'Activité hors exercice de la campagne.'
        );

        $pap = Pap::where('exercice_id', $suivi->exercice_id)->first();
        if ($pap && $pap->statut !== 'publie') {
            abort(422, 'Le PAP de cet exercice doit être publié avant la saisie de suivi.');
        }

        $collecte = Collecte::firstOrNew(['campagne_id' => $suivi->id, 'activite_id' => $activite->id]);
        if ($collecte->exists) {
            $this->authorize('update', $collecte);
        } else {
            $this->authorize('create', Collecte::class);
        }

        $data = $request->validate([
            'taux_physique' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quantite_prevue' => ['nullable', 'numeric', 'min:0'],
            'quantite_realisee' => ['nullable', 'numeric', 'min:0'],
            'unite' => ['nullable', 'string', 'max:50'],
            'date_realisation' => ['nullable', 'date'],
            'montant_realise' => ['required', 'numeric', 'min:0'],
            'cause_ecart' => ['nullable', 'string', 'max:2000'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['taux_physique']) && empty($data['quantite_prevue'])) {
            $data['taux_physique'] = null;
        }

        $collecte->fill($data);
        $collecte->setRelation('activite', $activite);
        $this->suiviPhysique->enrichirDepuisTaches($collecte);

        if ((float) ($collecte->taux_physique ?? 0) <= 0 && empty($collecte->quantite_prevue)) {
            return back()->withErrors(['taux_physique' => 'Indiquez un taux physique ou des quantités prévue/réalisée.'])->withInput();
        }
        $collecte->recalculer();
        $collecte->statut = 'saisie';
        $collecte->saisi_par ??= $request->user()->id;
        $collecte->save();

        return back()->with('succes', "Collecte enregistrée pour l'activité {$activite->code}.");
    }

    public function verifier(Request $request, Collecte $collecte): RedirectResponse
    {
        return $this->agirWorkflow($request, $collecte, demarrerSiBesoin: true);
    }

    public function valider(Request $request, Collecte $collecte): RedirectResponse
    {
        return $this->agirWorkflow($request, $collecte);
    }

    public function rejeter(Request $request, Collecte $collecte): RedirectResponse
    {
        $this->authorize('validate', $collecte);
        $instance = $collecte->workflowActif;

        if (! $instance) {
            $collecte->update(['statut' => 'saisie', 'verifie_par' => null, 'valide_par' => null]);

            return back()->with('succes', 'Collecte renvoyée pour correction.');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                $request->user(),
                'rejeter',
                $request->input('observation', 'Renvoyé pour correction.'),
                $request->input('categorie_rejet', WorkflowRejetCategorie::TECHNIQUE),
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Collecte renvoyée pour correction.');
    }

    private function agirWorkflow(Request $request, Collecte $collecte, bool $demarrerSiBesoin = false): RedirectResponse
    {
        $this->authorize('validate', $collecte);

        if ($demarrerSiBesoin) {
            abort_unless($collecte->statut === 'saisie', 422);
        } else {
            abort_unless($collecte->statut === 'verifiee', 422, 'La collecte doit être vérifiée avant validation.');
        }

        try {
            if ($demarrerSiBesoin && ! $collecte->workflowActif) {
                $this->workflow->demarrer($collecte, 'COLLECTE', $request->user());
            }

            $instance = $collecte->fresh()->workflowActif;
            if (! $instance) {
                throw new RuntimeException('Aucun circuit actif sur cette collecte.');
            }

            if (! $demarrerSiBesoin) {
                $this->justificatifs->assertPeutValider($collecte);
            }

            $this->workflow->traiterDecision($instance, $request->user(), 'valider');
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Décision enregistrée sur la collecte.');
    }
}
