<?php

namespace App\Http\Controllers;

use App\Depense\Etapes;
use App\Models\Engagement;
use App\Models\Liquidation;
use App\Models\LiquidationPiece;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\ServiceFait;
use App\Services\Depense\DepenseQuotaService;
use App\Services\Depense\DevisePaiementService;
use App\Services\Depense\ServiceFaitService;
use App\Services\DocumentPdf;
use App\Services\WebhookService;
use App\Services\WorkflowService;
use App\Http\Requests\WorkflowDecisionSansRenvoiRequest;
use App\Support\Numero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EtapeDepenseController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly WebhookService $webhooks,
        private readonly DepenseQuotaService $quotas,
    ) {
    }

    public function index(Request $request, string $type): View
    {
        $config = $this->config($type);
        $this->authorize('viewAny', $this->modele($type));

        $items = $config['model']::with('createur')->latest()->paginate(20);

        return view('depense.index', compact('config', 'type', 'items'));
    }

    public function create(Request $request, string $type): View
    {
        $config = $this->config($type);
        $this->authorize('create', $this->modele($type));

        $parent = $config['parent_model']::findOrFail($request->integer('parent'));
        abort_unless($parent->statut === $config['parent_statut_requis'], 422,
            "L'{$config['parent_label']} doit être au statut « {$config['parent_statut_requis']} ».");

        return view('depense.form', [
            'config' => $config, 'type' => $type, 'parent' => $parent,
            'montant' => $this->montantEtape($type, $parent),
            'montantMax' => $type === 'liquidation' && $parent instanceof Engagement
                ? $parent->montantRestantLiquidation()
                : null,
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $config = $this->config($type);
        $this->authorize('create', $this->modele($type));

        $parent = $config['parent_model']::findOrFail($request->integer('parent'));
        abort_unless($parent->statut === $config['parent_statut_requis'], 422);

        $data = $request->validate($this->regles($config, $type));

        $montant = $this->montantEtape($type, $parent, $request);
        unset($data['montant']);

        if (array_key_exists('date', $data)) {
            $data[$config['date_cle']] = $data['date'];
            unset($data['date']);
        }

        $racine = $this->racine($type, $parent);
        $annee = $racine->exercice?->annee ?? (int) date('Y');

        $item = $config['model']::create([
            ...$data,
            'numero' => Numero::suivant($config['table'], $config['prefixe'], $annee),
            $config['parent_cle'] => $parent->id,
            'montant' => $montant,
            'statut' => 'brouillon',
            'cree_par' => $request->user()->id,
        ]);

        if ($type === 'liquidation') {
            app(ServiceFaitService::class)->enregistrer($item, $request->all(), $request->user());
        }

        if ($type === 'paiement' && $parent instanceof Ordonnancement) {
            $ordre = $parent->ordresPaiement()->where('statut', 'emis')->first();
            if ($ordre) {
                $item->update(['ordre_paiement_id' => $ordre->id]);
            }
            app(DevisePaiementService::class)->appliquer($item, array_merge($request->all(), ['montant' => $montant]));
        }

        return redirect()->route('depense.show', [$type, $item->id])
            ->with('succes', $config['label'].' créé(e).');
    }

    public function show(Request $request, string $type, int $id): View
    {
        $config = $this->config($type);
        $item = $config['model']::with(['createur', 'workflowActif.etapes.acteur', 'pieces.auteur'])->findOrFail($id);
        if ($type === 'paiement') {
            $item->load('ecrituresComptables', 'details.ligneBudgetaire');
        }
        $this->authorize('view', $item);

        $engagement = $item->engagementRacine()->load('imputations.ligneBudgetaire.rubrique', 'imputations.ligneBudgetaire.structure');
        $instance = $item->workflowActif;
        $peutAgir = $instance && $request->user()->can('agir', $item);

        return view('depense.show', compact('config', 'type', 'item', 'engagement', 'instance', 'peutAgir'));
    }

    public function soumettre(Request $request, string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $item = $config['model']::findOrFail($id);
        $this->authorize('update', $item);

        try {
            $code = $config['workflow'];
            if ($item instanceof Ordonnancement) {
                $code = app(\App\Services\Depense\SeuilOrdonnancementService::class)->circuit($item);
            }
            $this->workflow->demarrer($item, $code, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', $config['label'].' soumis(e) à validation.');
    }

    public function agir(WorkflowDecisionSansRenvoiRequest $request, string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $item = $config['model']::findOrFail($id);
        $this->authorize('agir', $item);

        $data = $request->validated();

        $instance = $item->workflowActif;
        if (! $instance) {
            return back()->with('erreur', 'Aucun circuit actif.');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                $request->user(),
                $data['decision'],
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? null,
            );

            if ($data['decision'] === 'valider') {
                $item->update(['valide_par' => $request->user()->id]);
                $this->webhooks->declencher("{$type}.valide", [
                    'numero' => $item->numero, 'montant' => (float) $item->montant,
                ]);
                if ($item instanceof Paiement) {
                    app(\App\Services\Notifications\DepenseNotificationService::class)
                        ->notifierPaiementValide($item->fresh());
                }
            } elseif ($item instanceof Paiement) {
                $item->rejets()->create([
                    'motif' => \Illuminate\Support\Str::limit($data['observation'] ?? 'Rejet', 255, ''),
                    'observation' => $data['observation'] ?? null,
                    'rejete_par' => $request->user()->id,
                    'rejete_le' => now(),
                ]);
            }
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Décision enregistrée.');
    }

    public function pdf(Request $request, string $type, int $id, DocumentPdf $pdf): Response
    {
        $config = $this->config($type);
        $item = $config['model']::findOrFail($id);
        $this->authorize('view', $item);

        return $pdf->etapeDepense($item, $config)->stream("{$type}_{$item->numero}.pdf");
    }

    private function config(string $type): array
    {
        abort_unless(Etapes::existe($type), 404);

        return Etapes::get($type);
    }

    /** @return class-string<Model> */
    private function modele(string $type): string
    {
        return match ($type) {
            'liquidation' => Liquidation::class,
            'ordonnancement' => Ordonnancement::class,
            'paiement' => Paiement::class,
            default => abort(404),
        };
    }

    private function montantParent(Model $parent): float
    {
        return (float) ($parent->montant_total ?? $parent->montant);
    }

    private function montantEtape(string $type, Model $parent, Request $request): float
    {
        if ($type === 'liquidation' && $parent instanceof Engagement) {
            $restant = $parent->montantRestantLiquidation();
            abort_unless($restant > 0, 422, 'Le montant de l\'engagement est entièrement couvert par des liquidations.');

            $montant = $request->filled('montant')
                ? (float) $request->input('montant')
                : $restant;

            $this->quotas->assertPeutLiquider($parent, $montant);

            return $montant;
        }

        return $this->montantParent($parent);
    }

    private function racine(string $type, Model $parent): Engagement
    {
        return match ($type) {
            'liquidation' => $parent,
            'ordonnancement' => $parent->engagement,
            'paiement' => $parent->liquidation->engagement,
        };
    }

    private function regles(array $config, string $type): array
    {
        $regles = ['date' => ['nullable', 'date']];
        if ($type === 'liquidation') {
            $regles['montant'] = ['nullable', 'numeric', 'min:0.01'];
            $regles['service_fait_type'] = ['nullable', Rule::in(array_keys(ServiceFait::TYPES))];
            $regles['service_fait_reference'] = ['nullable', 'string', 'max:100'];
            $regles['service_fait_date'] = ['nullable', 'date'];
            $regles['piece_type'] = ['nullable', Rule::in(array_keys(LiquidationPiece::TYPES))];
            $regles['piece_reference'] = ['nullable', 'string', 'max:100'];
            $regles['piece_date'] = ['nullable', 'date'];
        }
        if ($type === 'paiement') {
            $regles['devise'] = ['required', Rule::in(array_keys(DevisePaiementService::DEVISES))];
            $regles['taux_change'] = ['nullable', 'numeric', 'min:0.000001'];
        }
        foreach ($config['champs'] as $champ => $meta) {
            if ($type === 'paiement' && in_array($champ, ['devise', 'taux_change'], true)) {
                continue;
            }
            $regles[$champ] = match ($meta['type']) {
                'select' => [($meta['requis'] ?? false) ? 'required' : 'nullable', Rule::in(array_keys($meta['options']))],
                'textarea', 'text' => ['nullable', 'string', 'max:1000'],
                default => ['nullable'],
            };
        }

        return $regles;
    }
}
