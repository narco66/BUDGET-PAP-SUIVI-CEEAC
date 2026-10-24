<?php

namespace App\Http\Controllers;

use App\Http\Requests\EngagementRequest;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Services\DocumentPdf;
use App\Services\EngagementService;
use App\Services\AccessScopeService;
use App\Services\WebhookService;
use App\Services\WorkflowService;
use App\Http\Requests\WorkflowDecisionSansRenvoiRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EngagementController extends Controller
{
    public function __construct(
        private readonly EngagementService $engagements,
        private readonly WorkflowService $workflow,
        private readonly WebhookService $webhooks,
        private readonly AccessScopeService $scopes,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Engagement::class);
        $engagements = Engagement::with('exercice', 'createur')->latest()->paginate(20);

        return view('engagements.index', compact('engagements'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Engagement::class);

        if (! $request->filled('besoin') && ! $request->user()->can('depenses.admin')) {
            abort(403, 'Un besoin validé est requis pour créer un engagement. Convertissez d\'abord une expression de besoin.');
        }

        $besoin = $request->filled('besoin') ? ExpressionBesoin::with('activite.ligneBudgetaire')->find($request->besoin) : null;
        $exercice = $besoin?->exercice ?? Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->first();

        $lignesQuery = LigneBudgetaire::with('rubrique')->where('exercice_id', $exercice?->id)->actif()->orderBy('code');
        $lignes = $this->scopes->filtrerStructures($lignesQuery, $request->user())->get();

        $prefill = null;
        if ($besoin) {
            $ligneId = $besoin->ligne_budgetaire_id ?? $besoin->activite?->ligne_budgetaire_id;
            $prefill = [
                'ligne_budgetaire_id' => $ligneId,
                'montant' => $besoin->montant_estime,
            ];
        }

        return view('engagements.create', [
            'besoin' => $besoin,
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'lignes' => $lignes,
            'prefill' => $prefill,
        ]);
    }

    public function store(EngagementRequest $request): RedirectResponse
    {
        $this->authorize('create', Engagement::class);

        try {
            $engagement = $this->engagements->creer(
                $request->safe()->only(['exercice_id', 'expression_besoin_id', 'type', 'objet', 'beneficiaire', 'date_engagement']),
                $request->validated('imputations'),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('engagements.show', $engagement)->with('succes', 'Engagement créé (brouillon).');
    }

    public function show(Engagement $engagement): View
    {
        $this->authorize('view', $engagement);
        $engagement->load([
            'exercice', 'createur', 'controleur', 'expressionBesoin', 'tache', 'activite',
            'imputations.ligneBudgetaire.rubrique', 'imputations.ligneBudgetaire.structure',
            'workflowActif.etapes.acteur', 'pieces.auteur',
        ]);
        $instance = $engagement->workflowActif;
        $peutAgir = $instance && $this->workflow->peutAgir($instance, auth()->user());

        return view('engagements.show', compact('engagement', 'instance', 'peutAgir'));
    }

    /** Soumet : réserve les crédits (contrôle de disponibilité) + démarre le visa. */
    public function soumettre(Engagement $engagement): RedirectResponse
    {
        $this->authorize('update', $engagement);

        try {
            $this->engagements->soumettre($engagement, auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Engagement soumis au visa du contrôleur financier (crédits réservés).');
    }

    /** Visa / rejet du contrôleur financier. */
    public function agir(WorkflowDecisionSansRenvoiRequest $request, Engagement $engagement): RedirectResponse
    {
        $this->authorize('agir', $engagement);
        $data = $request->validated();

        $instance = $engagement->workflowActif;
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

            if ($data['decision'] === 'valider') {
                $engagement->update(['controleur_id' => auth()->id()]);
                $this->webhooks->declencher('engagement.vise', [
                    'numero' => $engagement->numero,
                    'montant' => (float) $engagement->montant_total,
                    'objet' => $engagement->objet,
                ]);
            }
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Décision enregistrée.');
    }

    public function annuler(Engagement $engagement): RedirectResponse
    {
        $this->authorize('annuler', $engagement);
        $this->engagements->annuler($engagement, auth()->user());

        return back()->with('succes', 'Engagement annulé, crédits libérés.');
    }

    public function degager(Request $request, Engagement $engagement): RedirectResponse
    {
        $this->authorize('annuler', $engagement);
        $data = $request->validate(['montant' => ['required', 'numeric', 'min:0.01']]);

        try {
            $this->engagements->degager($engagement, (float) $data['montant'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Reliquat non liquidé dégagé.');
    }

    public function pdf(Engagement $engagement, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $engagement);

        return $pdf->engagement($engagement)->stream("engagement_{$engagement->numero}.pdf");
    }

    public function certificat(Engagement $engagement, DocumentPdf $pdf): Response
    {
        $this->authorize('view', $engagement);

        return $pdf->certificatReservation($engagement)->stream("certificat_{$engagement->numero}.pdf");
    }
}
