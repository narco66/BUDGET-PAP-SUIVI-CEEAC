<?php

namespace App\Http\Controllers;

use App\Models\Delegation;
use App\Models\Interim;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Models\WorkflowTacheEvenement;
use App\Services\Workflow\TacheActionService;
use App\Services\Workflow\TacheQueryService;
use App\Services\Workflow\TacheWorkflowService;
use App\Support\WorkflowDecisionRules;
use App\Support\WorkflowObjetUrls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Interface Blade du module « Mes Tâches ». */
class TacheController extends Controller
{
    private const FILTRES = ['module', 'etape', 'statut', 'decision', 'priorite', 'echeance', 'structure_id', 'du', 'au', 'q', 'affectation', 'montant_min', 'montant_max', 'tri', 'sens'];

    public function __construct(
        private readonly TacheQueryService $requetes,
        private readonly TacheActionService $actions,
        private readonly TacheWorkflowService $taches,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $vue = in_array($request->input('vue'), TacheQueryService::VUES, true) ? $request->input('vue') : 'mes';
        abort_if($vue === 'unite' && ! $this->requetes->peutVoirUnite($user), 403);
        $filtres = array_filter($request->only(self::FILTRES), fn ($v) => $v !== null && $v !== '');
        if ($vue === 'traitees') {
            $filtres += ['tri' => 'termine', 'sens' => 'desc'];
        }

        return view('taches.index', [
            'vue' => $vue,
            'filtres' => $filtres,
            'taches' => $this->requetes->lister($user, $vue, $filtres, 20, max(1, $request->integer('page', 1))),
            'stats' => $this->requetes->statistiques($user, $request->input('depuis')),
            'compteurs' => $this->requetes->compteurs($user, array_diff_key($filtres, array_flip(['tri', 'sens']))),
            'situationDossier' => fn (WorkflowTache $t) => $this->requetes->situationDossier($t),
            'peutUnite' => $this->requetes->peutVoirUnite($user),
            'peutSuperviser' => $this->requetes->peutSuperviser($user),
            'delegations' => $this->delegationsEnCours($user),
            'options' => $this->options(),
            'prioriteEffective' => fn (WorkflowTache $t) => $this->taches->prioriteEffective($t),
            'montant' => fn (WorkflowTache $t) => $this->requetes->montant($t),
            'initiateur' => fn (WorkflowTache $t) => $this->requetes->initiateur($t)?->name,
            'actualiseLe' => now(),
        ]);
    }

    public function show(Request $request, WorkflowTache $tache): View
    {
        $user = $request->user();
        abort_unless($this->requetes->peutConsulter($tache, $user), 403);
        $tache->load(['instance.workflow', 'instance.etapes.acteur', 'etape', 'objet', 'structure', 'assigne', 'terminePar']);
        $objet = $tache->objet;
        $tachesInstance = $tache->tachesLiees()->pluck('reference', 'id');

        return view('taches.show', [
            'tache' => $tache,
            'objet' => $objet,
            'urlDossier' => \App\Services\Notifications\NotificationDispatcher::lienRelatif(WorkflowObjetUrls::show($objet)),
            'eligibilite' => $this->requetes->eligibilite($tache, $user),
            'actions' => $this->actions->actionsDisponibles($tache, $user),
            'libelleValidation' => $this->actions->libelleValidation($tache),
            'priorite' => $this->taches->prioriteEffective($tache),
            'montant' => $this->requetes->montant($tache),
            'finances' => $this->finances($objet),
            'pieces' => $objet && method_exists($objet, 'pieces') ? $objet->pieces()->get() : collect(),
            'documentsGed' => $objet && method_exists($objet, 'gedDocuments') ? $objet->gedDocuments()->get() : collect(),
            'evenements' => WorkflowTacheEvenement::query()->with(['user', 'pourCompteDe'])
                ->whereIn('workflow_tache_id', $tachesInstance->keys())->orderBy('created_at')->orderBy('id')->get(),
            'tachesInstance' => $tachesInstance,
            'candidats' => $this->requetes->peutGererAffectations($tache, $user) ? $this->candidatsReaffectation($tache) : collect(),
        ]);
    }

    public function supervision(Request $request): View
    {
        abort_unless($this->requetes->peutSuperviser($request->user()), 403);

        return view('taches.supervision', ['s' => $this->requetes->supervision($request->user(), $request->input('depuis'))]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $vue = in_array($request->input('vue'), TacheQueryService::VUES, true) ? $request->input('vue') : 'mes';
        abort_if($vue === 'unite' && ! $this->requetes->peutVoirUnite($user), 403);
        $filtres = array_filter($request->only(self::FILTRES), fn ($v) => $v !== null && $v !== '');
        // Mêmes règles de périmètre que la consultation (RG-15).
        $lignes = $this->requetes->lister($user, $vue, $filtres, 5000, 1)->getCollection();

        return response()->streamDownload(function () use ($lignes) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Référence', 'Module', 'Dossier', 'Objet', 'Étape', 'Rôle', 'Unité', 'Affectataire', 'Reçue le', 'Échéance', 'Priorité', 'Montant', 'Statut', 'Décision', 'Terminée le'], ';');
            foreach ($lignes as $t) {
                fputcsv($out, [
                    $t->reference, $t->module, $t->objet?->numero ?? $t->objet_id, $t->objet_libelle, $t->libelle_etape, $t->role,
                    $t->structure?->code, $t->assigne?->name, \App\Support\Horodatage::local($t->created_at, 'd/m/Y H:i'), \App\Support\Horodatage::local($t->echeance_le, 'd/m/Y H:i'),
                    $this->taches->prioriteEffective($t), $this->requetes->montant($t), WorkflowTache::STATUTS[$t->statut] ?? $t->statut,
                    $t->decision ? (WorkflowTache::DECISIONS[$t->decision] ?? $t->decision) : '', $t->termine_le ? \App\Support\Horodatage::local($t->termine_le, 'd/m/Y H:i') : '',
                ], ';');
            }
            fclose($out);
        }, 'mes-taches-'.$vue.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function agir(Request $request, WorkflowTache $tache, string $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->requetes->peutConsulter($tache, $user), 403);
        $version = $request->filled('version') ? $request->integer('version') : null;

        try {
            $message = match ($action) {
                'prendre_en_charge' => [$this->actions->prendreEnCharge($tache, $user, $version), 'Tâche prise en charge.'][1],
                'liberer' => [$this->actions->liberer($tache, $user, $version), 'Tâche libérée.'][1],
                'suspendre' => [$this->actions->suspendre($tache, $user, (string) $request->input('motif'), $version), 'Tâche suspendue.'][1],
                'reprendre' => [$this->actions->reprendre($tache, $user, $version), 'Tâche reprise.'][1],
                'commenter' => [$this->actions->commenter($tache, $user, (string) $request->input('commentaire')), 'Observation enregistrée.'][1],
                'prioriser' => [$this->actions->prioriser($tache, $user, (string) $request->input('priorite'), (string) $request->input('motif'), $version), 'Priorité modifiée.'][1],
                'reaffecter' => [$this->actions->reaffecter($tache, $user, User::findOrFail($request->integer('user_id')), (string) $request->input('motif'), $version), 'Tâche réaffectée.'][1],
                'decider' => $this->decider($request, $tache, $user, $version),
                default => abort(404),
            };
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage())->withInput();
        }

        return $action === 'decider'
            ? redirect()->route('taches.index')->with('succes', $message)
            : back()->with('succes', $message);
    }

    private function decider(Request $request, WorkflowTache $tache, User $user, ?int $version): string
    {
        $data = $request->validate(WorkflowDecisionRules::regles(avecRenvoi: true));
        $this->actions->decider($tache, $user, $data, $version);

        return match ($data['decision']) {
            'valider' => "Décision enregistrée : {$tache->reference} validée ; l'étape suivante a été affectée.",
            'rejeter' => "Dossier rejeté ({$tache->reference}).",
            default => "Dossier retourné pour correction ({$tache->reference}).",
        };
    }

    /** @return array<string, mixed> données budgétaires ou de paiement issues du dossier source (RG-10) */
    private function finances(?object $objet): array
    {
        if (! $objet) {
            return [];
        }
        $f = [];
        $engagement = match (true) {
            $objet instanceof \App\Models\Engagement => $objet,
            $objet instanceof \App\Models\Liquidation => $objet->engagement,
            $objet instanceof \App\Models\Ordonnancement => $objet->liquidation?->engagement,
            $objet instanceof \App\Models\Paiement => $objet->ordonnancement?->liquidation?->engagement,
            default => null,
        };
        $ligne = $engagement?->imputations()->with('ligneBudgetaire.rubrique')->first()?->ligneBudgetaire
            ?? ($objet->ligneBudgetaire ?? null);
        if ($ligne) {
            $ouvert = (float) ($ligne->montant_revise ?? $ligne->montant_ouvert);
            $f['ligne'] = [
                'code' => $ligne->code, 'intitule' => $ligne->intitule ?? $ligne->rubrique?->intitule,
                'initial' => (float) $ligne->montant_ouvert, 'revise' => $ligne->montant_revise !== null ? (float) $ligne->montant_revise : null,
                'engage' => (float) $ligne->montant_engage, 'disponible' => $ouvert - (float) $ligne->montant_engage,
            ];
        }
        if ($objet instanceof \App\Models\Paiement || $objet instanceof \App\Models\Ordonnancement) {
            $ord = $objet instanceof \App\Models\Paiement ? $objet->ordonnancement : $objet;
            if ($ord) {
                $paye = (float) \App\Models\Paiement::query()->where('ordonnancement_id', $ord->id)->where('statut', 'valide')
                    ->where(fn ($q) => $q->whereNull('etat_execution')->orWhere('etat_execution', '!=', 'instrument_rejete'))->sum('montant');
                $f['paiement'] = ['ordonnance' => (float) $ord->montant, 'paye' => $paye, 'reste' => max(0, (float) $ord->montant - $paye)];
            }
        }
        if ($engagement) {
            $f['engagement'] = ['numero' => $engagement->numero, 'montant' => (float) $engagement->montant_total, 'statut' => $engagement->statut];
        }

        return $f;
    }

    private function candidatsReaffectation(WorkflowTache $tache)
    {
        return User::query()->where('actif', true)->role($tache->role)->orderBy('name')->get()
            ->filter(fn (User $u) => $this->requetes->peutTraiter((clone $tache)->forceFill(['assigne_a' => null]), $u))->values();
    }

    private function delegationsEnCours(User $user)
    {
        return Delegation::query()->enCours()->with('deleguant')->where('delegue_a', $user->id)->get()
            ->map(fn ($d) => ['titulaire' => $d->deleguant?->name, 'fin' => $d->date_fin, 'type' => 'Délégation'])
            ->merge(Interim::query()->enCours()->with('titulaire')->where('interim_user_id', $user->id)->get()
                ->map(fn ($i) => ['titulaire' => $i->titulaire?->name, 'fin' => $i->date_fin, 'type' => 'Intérim']));
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'modules' => ['EB' => 'Expression de besoin', 'ENG' => 'Engagement', 'LIQ' => 'Liquidation', 'ORD' => 'Ordonnancement', 'PAY' => 'Paiement', 'PAP' => 'PAP', 'BUDGET' => 'Budget', 'REC' => 'Recettes', 'SE' => 'Suivi-évaluation', 'STRAT' => 'Planification stratégique', 'IMPORT' => 'Import de données'],
            'etapes' => WorkflowTache::query()->distinct()->orderBy('role')->pluck('role')->all(),
            'structures' => \App\Models\Structure::query()->whereIn('id', WorkflowTache::query()->whereNotNull('structure_id')->distinct()->pluck('structure_id'))->orderBy('code')->get(['id', 'code', 'libelle']),
            'priorites' => WorkflowTache::PRIORITES,
            'statuts' => WorkflowTache::STATUTS,
        ];
    }
}
