<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkflowDecisionSansRenvoiRequest;
use App\Models\Exercice;
use App\Models\ImportLigne;
use App\Models\ImportLot;
use App\Models\ImportMapping;
use App\Models\User;
use App\Services\Import\ImportDomaineRegistry;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportLotService;
use App\Services\Import\ImportMappingService;
use App\Services\Import\ImportRapportService;
use App\Services\Workflow\DecisionMetierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Écrans du module Import : tableau de bord, nouvelle importation, fiche du lot et actions du cycle. */
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportDomaineRegistry $domaines,
        private readonly ImportLotService $lots,
        private readonly ImportExecutionService $execution,
        private readonly ImportMappingService $mappings,
        private readonly ImportRapportService $rapports,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ImportLot::class);
        $user = $request->user();
        $q = ImportLot::query()->with(['createur', 'exercice'])
            ->when(! $user->can('import.history'), fn ($b) => $b->where('cree_par', $user->id))
            ->when($request->input('domaine'), fn ($b, $v) => $b->where('domaine', $v))
            ->when($request->input('statut'), fn ($b, $v) => $b->where('statut', $v))
            ->when($request->input('utilisateur'), fn ($b, $v) => $b->where('cree_par', (int) $v))
            ->when($request->input('du'), fn ($b, $v) => $b->where('created_at', '>=', now()->parse($v)->startOfDay()))
            ->when($request->input('au'), fn ($b, $v) => $b->where('created_at', '<=', now()->parse($v)->endOfDay()));

        $stats = (clone $q)->selectRaw('statut, count(*) n')->groupBy('statut')->pluck('n', 'statut');
        $parDomaine = (clone $q)->selectRaw('domaine, count(*) n')->groupBy('domaine')->pluck('n', 'domaine');
        $lignesImportees = (clone $q)->where('statut', 'termine')->get(['totaux'])->sum(fn ($l) => (int) ($l->totaux['executees'] ?? 0));
        $anomalies = (clone $q)->get(['totaux'])->sum(fn ($l) => (int) ($l->totaux['bloquantes'] ?? 0) + (int) ($l->totaux['majeures'] ?? 0));

        return view('imports.index', [
            'lots' => $q->latest('id')->paginate(20)->withQueryString(),
            'stats' => $stats,
            'parDomaine' => $parDomaine,
            'lignesImportees' => $lignesImportees,
            'anomalies' => $anomalies,
            'domaines' => $this->domaines->disponibles(),
            'utilisateurs' => $user->can('import.history') ? User::query()->whereIn('id', ImportLot::query()->distinct()->pluck('cree_par'))->orderBy('name')->get(['id', 'name']) : collect(),
            'filtres' => $request->only(['domaine', 'statut', 'utilisateur', 'du', 'au']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ImportLot::class);

        return view('imports.create', [
            'domaines' => collect($this->domaines->disponibles())->filter(fn ($d) => $d->autorise($request->user())),
            'planifies' => ImportDomaineRegistry::PLANIFIES,
            'exercices' => Exercice::query()->orderByDesc('annee')->get(['id', 'annee', 'statut']),
            'modeles' => ImportMapping::query()->orderBy('nom')->get()->groupBy('domaine'),
            'domaineChoisi' => $request->input('domaine'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ImportLot::class);
        $data = $request->validate([
            'domaine' => ['required', 'string', Rule::in(array_keys($this->domaines->disponibles()))],
            'mode' => ['required', Rule::in(array_keys(ImportLot::MODES))],
            'exercice_id' => ['nullable', 'integer', 'exists:exercices,id'],
            'fichier' => ['required', 'file', 'max:'.(config('import.taille_max_mo') * 1024)],
            'feuille' => ['nullable', 'string', 'max:120'],
            'modele_id' => ['nullable', 'integer', 'exists:import_mappings,id'],
        ]);

        try {
            $lot = $this->lots->creer($data['domaine'], $data['mode'], $data['exercice_id'] ?? null, $request->user());
            $lot = $this->lots->deposer($lot, $request->file('fichier'), $data['feuille'] ?? null, $data['modele_id'] ?? null);
        } catch (RuntimeException $e) {
            if (isset($lot)) {
                $this->lots->annuler($lot->fresh(), $request->user(), 'Dépôt refusé : '.$e->getMessage());
            }

            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->route('imports.show', $lot)->with('succes', $lot->statut === 'mapping'
            ? 'Fichier chargé : complétez la correspondance des colonnes.'
            : 'Fichier analysé : vérifiez la prévisualisation et les anomalies.');
    }

    public function show(Request $request, ImportLot $lot): View
    {
        $this->authorize('view', $lot);
        $domaine = $this->domaines->get($lot->domaine);
        $user = $request->user();
        $onglet = in_array($request->input('onglet'), ['synthese', 'correspondance', 'apercu', 'anomalies', 'historique'], true)
            ? $request->input('onglet') : ($lot->statut === 'mapping' ? 'correspondance' : 'synthese');
        $instance = $lot->workflowActif;

        return view('imports.show', [
            'lot' => $lot->load(['createur', 'approbateur', 'executant', 'exercice', 'evenements.user']),
            'domaine' => $domaine,
            'onglet' => $onglet,
            'lignes' => $lot->lignes()->when($request->input('operation'), fn ($b, $v) => $b->where('operation', $v))
                ->orderBy('numero')->paginate(config('import.par_page'), ['*'], 'page_lignes')->withQueryString(),
            'anomalies' => $lot->anomalies()->when($request->input('gravite'), fn ($b, $v) => $b->where('gravite', $v))
                ->orderByRaw("CASE gravite WHEN 'bloquante' THEN 1 WHEN 'majeure' THEN 2 ELSE 3 END")->orderBy('numero_ligne')
                ->paginate(config('import.par_page'), ['*'], 'page_anomalies')->withQueryString(),
            'colonnesIgnorees' => $this->mappings->colonnesIgnorees($lot->mapping ?? [], $lot->entetes ?? []),
            'manquants' => $this->mappings->champsRequisManquants($domaine, $lot->mapping ?? []),
            'instance' => $instance,
            'peutPreparer' => $user->can('preparer', $lot) && in_array($lot->statut, ['brouillon', 'fichier_charge', 'mapping', 'anomalies_detectees', 'simule'], true),
            // Seuls les lots sensibles passent par une soumission (approbation) ; les autres s'exécutent directement.
            'peutSoumettre' => $domaine->circuit() !== null && $user->can('soumettre', $lot) && $lot->statut === 'simule'
                && $lot->mode !== 'simulation' && $lot->total('a_ecrire') > 0,
            'peutAgir' => $user->can('agir', $lot),
            'peutExecuter' => $user->can('executer', $lot) && $this->execution->peutExecuter($lot) && $domaine->autorise($user),
            'peutAnnuler' => $user->can('annuler', $lot) && ! $lot->estFinal() && $lot->statut !== 'en_cours',
            'peutRapport' => $user->can('telechargerRapport', $lot),
            'modeles' => ImportMapping::query()->where('domaine', $lot->domaine)->orderBy('nom')->get(),
        ]);
    }

    public function deposer(Request $request, ImportLot $lot): RedirectResponse
    {
        $this->authorize('preparer', $lot);
        $data = $request->validate([
            'fichier' => ['required', 'file', 'max:'.(config('import.taille_max_mo') * 1024)],
            'feuille' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->executerAction(fn () => $this->lots->deposer($lot, $request->file('fichier'), $data['feuille'] ?? null), 'Nouveau fichier chargé et analysé.');
    }

    public function mapping(Request $request, ImportLot $lot): RedirectResponse
    {
        $this->authorize('preparer', $lot);
        $data = $request->validate(['mapping' => ['array'], 'nom_modele' => ['nullable', 'string', 'max:120']]);

        return $this->executerAction(fn () => $this->lots->definirMapping($lot, $data['mapping'] ?? [], $request->user(), $data['nom_modele'] ?? null), 'Correspondance enregistrée ; lot réanalysé.');
    }

    public function analyser(Request $request, ImportLot $lot): RedirectResponse
    {
        $this->authorize('preparer', $lot);

        return $this->executerAction(fn () => $this->lots->analyser($lot), 'Lot réanalysé sur l\'état actuel de la base.');
    }

    public function soumettre(Request $request, ImportLot $lot): RedirectResponse
    {
        $this->authorize('soumettre', $lot);
        $sensible = $this->domaines->get($lot->domaine)->circuit() !== null;

        return $this->executerAction(fn () => $this->lots->soumettre($lot, $request->user(), $request->boolean('conflits_acceptes')),
            $sensible ? 'Lot soumis à approbation : l\'approbateur a reçu une tâche.' : 'Lot confirmé : il peut être exécuté.');
    }

    public function agir(WorkflowDecisionSansRenvoiRequest $request, ImportLot $lot): RedirectResponse
    {
        $this->authorize('agir', $lot);

        return $this->executerAction(fn () => app(DecisionMetierService::class)->decider($lot, $request->user(), $request->validated()), 'Décision enregistrée.');
    }

    public function executer(Request $request, ImportLot $lot): RedirectResponse
    {
        $this->authorize('executer', $lot);
        $request->validate(['confirmation' => ['accepted']], ['confirmation.accepted' => 'Confirmez l\'intégration définitive des données.']);
        try {
            $lot = $this->execution->lancer($lot, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with($lot->statut === 'echec' ? 'erreur' : 'succes', match ($lot->statut) {
            'termine' => 'Import terminé : '.$lot->total('executees').' ligne(s) intégrée(s).',
            'en_cours' => 'Exécution lancée en arrière-plan : suivez l\'avancement sur cette page.',
            default => 'Échec : aucune donnée intégrée. '.$lot->motif,
        });
    }

    public function annuler(Request $request, ImportLot $lot): RedirectResponse
    {
        $this->authorize('annuler', $lot);
        $data = $request->validate(['motif' => ['required', 'string', 'max:1000']]);

        return $this->executerAction(fn () => $this->lots->annuler($lot, $request->user(), $data['motif']), 'Lot annulé.');
    }

    public function rapport(Request $request, ImportLot $lot, string $type, string $format): StreamedResponse
    {
        $this->authorize('telechargerRapport', $lot);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        return match ($type) {
            'anomalies' => $this->rapports->anomalies($lot, $format),
            'operations' => $this->rapports->operations($lot, $format),
            default => abort(404),
        };
    }

    public function modele(Request $request, string $domaine): StreamedResponse
    {
        $this->authorize('viewAny', ImportLot::class);
        abort_unless($this->domaines->existe($domaine), 404);

        return $this->rapports->modele($domaine);
    }

    private function executerAction(callable $action, string $succes): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', $succes);
    }
}
