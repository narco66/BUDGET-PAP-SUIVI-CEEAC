<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpressionBesoinRequest;
use App\Http\Requests\WorkflowDecisionRequest;
use App\Models\Activite;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\Tache;
use App\Services\AccessScopeService;
use App\Services\DocumentPdf;
use App\Services\WorkflowService;
use App\Support\Numero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ExpressionBesoinController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly AccessScopeService $scopes,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExpressionBesoin::class);

        $user = $request->user();
        $liste = app(\App\Services\Depense\BesoinListeService::class)->lister($request, $user);

        // Référentiels des filtres seulement (le formulaire de saisie charge les siens dans options()).
        return view('besoins.index', $liste + [
            'priorites' => ExpressionBesoin::PRIORITES,
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee']),
            'structures' => \App\Models\Structure::query()->whereIn('id', $this->scopes->filtrerStructuresMetier(ExpressionBesoin::query(), $user)->select('structure_id'))
                ->orderBy('code')->get(['id', 'code', 'libelle']),
            'taches' => Tache::query()->whereIn('id', $this->scopes->filtrerStructuresMetier(ExpressionBesoin::query(), $user)->whereNotNull('tache_id')->select('tache_id'))
                ->orderBy('code')->get(['id', 'code', 'intitule']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ExpressionBesoin::class);

        return view('besoins.create', $this->options());
    }

    public function store(ExpressionBesoinRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpressionBesoin::class);
        try {
            app(\App\Services\Budget\ExerciceService::class)->assertExerciceOuvert((int) $request->exercice_id);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage())->withInput();
        }
        $annee = Exercice::find($request->exercice_id)?->annee ?? (int) date('Y');

        try {
            $besoin = DB::transaction(function () use ($request, $annee) {
                $besoin = ExpressionBesoin::create([
                    ...$this->donneesBesoin($request),
                    'numero' => Numero::suivant('expressions_besoin', 'EB', $annee),
                    'demandeur_id' => $request->user()->id,
                    'statut' => 'brouillon',
                ]);
                $this->enregistrerTachesActivite($request, $besoin);

                return $besoin;
            });
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage())->withInput();
        }

        return redirect()->route('besoins.show', $besoin)->with('succes', 'Expression du besoin creee.');
    }

    public function show(ExpressionBesoin $besoin): View
    {
        $this->authorize('view', $besoin);
        $besoin->load('structure', 'demandeur', 'activite', 'tache', 'exercice', 'workflowActif.etapes.acteur', 'pieces.auteur');
        $instance = $besoin->workflowActif;
        $peutAgir = $instance && $this->workflow->peutAgir($instance, auth()->user());
        // Suivi de la chaîne aval (ENG → LIQ → ORD → PAY) dès qu'un engagement existe : où en est le dossier, qui doit agir.
        $suivi = $besoin->engagements()->exists()
            ? app(\App\Services\Depense\DepenseDossierService::class)->depuisBesoin($besoin)
            : null;
        if ($suivi && $suivi['engagement']) {
            $suivi['cloture'] = app(\App\Services\Depense\ChaineClotureService::class)->etat($suivi['engagement']);
        }

        return view('besoins.show', compact('besoin', 'instance', 'peutAgir', 'suivi'));
    }

    public function edit(ExpressionBesoin $besoin): View
    {
        $this->authorize('update', $besoin);

        return view('besoins.edit', array_merge(['besoin' => $besoin], $this->options($besoin->activite_id)));
    }

    public function update(ExpressionBesoinRequest $request, ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('update', $besoin);
        try {
            DB::transaction(function () use ($request, $besoin) {
                $besoin->update($this->donneesBesoin($request));
                $this->enregistrerTachesActivite($request, $besoin);
            });
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage())->withInput();
        }

        return redirect()->route('besoins.show', $besoin)->with('succes', 'Expression du besoin mise a jour.');
    }

    public function destroy(ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('delete', $besoin);
        if ($besoin->engagements()->exists()) {
            return back()->with('erreur', 'Annulation de l’EB interdite : un engagement lié existe. Annuler d’abord l’engagement et ses effets.');
        }
        $besoin->delete();

        return redirect()->route('besoins.index')->with('succes', 'Expression du besoin supprimee.');
    }

    public function soumettre(ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('update', $besoin);

        try {
            app(\App\Services\Budget\ExerciceService::class)->assertExerciceOuvert((int) $besoin->exercice_id);
            app(\App\Services\Depense\HeritagePapService::class)->figer($besoin);
            if ($besoin->enCorrection() && $besoin->workflowActif) {
                // Dossier corrigé après un retour : le circuit reprend au niveau qui l'avait renvoyé.
                $this->workflow->reprendreApresCorrection($besoin->fresh(), auth()->user());
            } else {
                $circuit = app(\App\Services\Depense\BesoinCircuitService::class)->code($besoin->fresh());
                $this->workflow->demarrer($besoin->fresh(), $circuit, auth()->user());
            }
            app(\App\Services\Notifications\DepenseNotificationService::class)
                ->notifierBesoinSoumis($besoin->fresh());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Besoin soumis pour validation.');
    }

    public function agir(WorkflowDecisionRequest $request, ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('agir', $besoin);
        $data = $request->validated();

        $instance = $besoin->workflowActif;
        if (! $instance) {
            return back()->with('erreur', 'Aucun circuit actif.');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                auth()->user(),
                $data['decision'],
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Decision enregistree.');
    }

    public function convertir(ExpressionBesoin $besoin): RedirectResponse
    {
        $this->authorize('view', $besoin);
        abort_unless($besoin->statut === 'valide', 422, 'Le besoin doit etre valide.');
        abort_unless(auth()->user()->can('engagement.creer'), 403);

        $besoin->update(['statut' => 'converti']);

        return redirect()->route('engagements.create', ['besoin' => $besoin->id])
            ->with('succes', "Besoin converti : completez l'engagement.");
    }

    public function pdf(ExpressionBesoin $besoin, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $besoin);

        return $pdf->besoin($besoin)->stream("besoin_{$besoin->numero}.pdf");
    }

    /**
     * Données validées de l'EB (sans les tâches) ; le responsable de la ligne PAP est renseigné dans le
     * référentiel RBM lorsqu'il manquait.
     *
     * @return array<string, mixed>
     */
    private function donneesBesoin(ExpressionBesoinRequest $request): array
    {
        $donnees = collect($request->validated())->except(['taches_nouvelles', 'tache_nouvelle_concernee', 'activite_responsable_id'])->all();
        $activite = Activite::find($donnees['activite_id'] ?? null);
        if ($activite && ! $activite->responsable_user_id && $request->validated('activite_responsable_id')) {
            $activite->update(['responsable_user_id' => (int) $request->validated('activite_responsable_id')]);
        }

        return $donnees;
    }

    /**
     * Tâches de l'activité exprimée par le besoin, saisies en sous-lignes de la ligne PAP : rattachées au besoin,
     * leur total égale le montant estimé ; la tâche désignée comme concernée est rattachée au besoin.
     */
    private function enregistrerTachesActivite(ExpressionBesoinRequest $request, ExpressionBesoin $besoin): void
    {
        $sousLignes = array_values((array) $request->validated('taches_nouvelles', []));
        if ($sousLignes === [] || ! $besoin->activite) {
            return;
        }
        $taches = app(\App\Services\Rbm\TacheBudgetService::class)
            ->enregistrerSousLignes($besoin->activite, $besoin->fresh(), $sousLignes, $request->user()->id);
        $index = $request->validated('tache_nouvelle_concernee');
        $besoin->update(['tache_id' => $index !== null && $index !== '' ? $taches->get((int) $index)?->id : null]);
    }

    private function options(?int $activiteConservee = null): array
    {
        $user = auth()->user();
        $structures = $this->scopes
            ->filtrerReferentielStructures(Structure::actif()->orderBy('libelle'), $user)
            ->get(['id', 'code', 'libelle']);

        $activites = $this->scopes
            ->filtrerStructuresMetier(Activite::with('produit.axe.pilier', 'sousProduit.produit.axe.pilier', 'responsable:id,name')->orderBy('code'), $user)
            ->limit(1000)
            ->get(['id', 'code', 'intitule', 'ligne_budgetaire_id', 'produit_id', 'sous_produit_id', 'montant_total', 'responsable_user_id']);

        $taches = Tache::orderBy('code')->limit(1000)->get(['id', 'activite_id', 'code', 'intitule', 'montant']);

        // « Activité rattachée » : activités du PAP (investissement) et lignes du budget (fonctionnement, équipements),
        // présentées sous leurs codes et libellés des documents importés.
        $imputations = app(\App\Services\Depense\ImputationPapService::class)->options($activites, $this->scopes
            ->filtrerStructuresMetier(LigneBudgetaire::query()->where('actif', true), $user)
            ->get(['id', 'exercice_id', 'activite_id', 'code', 'intitule', 'montant_ouvert', 'montant_revise', 'montant_engage']), $activiteConservee);

        return [
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => $structures,
            'activites' => $activites,
            'imputations' => $imputations,
            'responsables' => \App\Models\User::query()->where('actif', true)->orderBy('name')->get(['id', 'name']),
            'taches' => $taches,
            'priorites' => ExpressionBesoin::PRIORITES,
        ];
    }
}
