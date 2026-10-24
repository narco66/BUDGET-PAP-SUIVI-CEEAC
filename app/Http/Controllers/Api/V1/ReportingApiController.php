<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\AuditReporting;
use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\RapportCategorie;
use App\Models\RapportModele;
use App\Services\Reporting\ActivitesRetardService;
use App\Services\Reporting\CockpitLiveService;
use App\Services\Reporting\RapportAuditService;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\ReportingService;
use App\Services\TableauBordService;
use App\Support\Montant;
use App\Support\PersonaTableauxBord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Endpoints REST catalogue, cockpit et historique Reporting (P3). */
class ReportingApiController extends Controller
{
    /** @var list<string> */
    private const MONTANTS = [
        'ouvert', 'alloue', 'revise_delta', 'engage', 'liquide', 'ordonnance', 'paye',
        'decaisse', 'disponible', 'reste_a_engager', 'reste_a_payer', 'montant',
    ];

    public function __construct(
        private readonly RapportAuditService $audit,
        private readonly ReportingService $reporting,
        private readonly ActivitesRetardService $retards,
        private readonly TableauBordService $tableauBord,
        private readonly CockpitLiveService $live,
    ) {
    }

    public function hub(Request $request): JsonResponse
    {
        $this->assertReporting($request);
        $peutGenerer = $this->peut($request, 'reporting.generate');
        $exercice = $this->exercice($request);

        $types = collect(RapportComposer::TYPES)->map(fn (string $titre, string $type) => [
            'type' => $type,
            'titre' => $titre,
            'href_apercu' => $this->href('reporting.show', $type),
            'href_generer' => $peutGenerer ? $this->href('reporting.generer', $type) : null,
        ])->values()->all();

        $recents = Rapport::with(['exercice', 'generePar'])->latest()->limit(8)->get()
            ->map(fn (Rapport $r) => $this->formatRapport($r))
            ->values()
            ->all();

        return response()->json([
            'exercice' => $exercice?->only(['id', 'annee', 'libelle', 'statut']),
            'indicateurs' => [
                'total' => Rapport::query()->count(),
                'signes' => Rapport::query()->where('statut', 'signe')->count(),
                'publies' => Rapport::query()->where('statut_workflow', 'publie')->count(),
            ],
            'types' => $types,
            'recents' => $recents,
            'peut_generer' => $peutGenerer,
            'peut_cockpit' => $this->autoriseCockpit($request),
            'peut_planifier' => $this->autorisePlanification($request),
            'href_cockpit' => $this->href('reporting.cockpit'),
            'href_planification' => $this->href('reporting.planification'),
            'href_modeles' => $this->href('reporting.modeles.index'),
        ]);
    }

    public function cockpit(Request $request): JsonResponse
    {
        abort_unless($this->autoriseCockpit($request), 403);
        $exercice = $this->exerciceObligatoire($request);
        $filtres = $this->filtresRetard($request);
        $persona = PersonaTableauxBord::pour($request->user());
        $data = $this->reporting->cockpit($exercice->id);
        if ($persona['persona_key'] === 'executif') {
            $ordre = ['danger' => 0, 'warning' => 1, 'success' => 2];
            usort($data['alertes'], fn ($a, $b) => ($ordre[$a['niveau']] ?? 9) <=> ($ordre[$b['niveau']] ?? 9));
        }

        $eval = EvaluationPeriodique::query()
            ->where('exercice_id', $exercice->id)
            ->orderByDesc('genere_le')
            ->first();
        $papSe = $this->tableauBord->executionPap($exercice->id);
        $retards = $this->retards->paginer($exercice->id, $filtres);
        $retardSynthese = $this->retards->synthese($exercice->id, $filtres);
        $qs = array_filter(['exercice' => $exercice->id] + $filtres);

        return response()->json([
            'data' => [
                'exercice' => $exercice->only(['id', 'annee', 'libelle', 'statut']),
                'persona' => [
                    'persona' => $persona['persona'],
                    'persona_key' => $persona['persona_key'],
                ],
                'global' => $this->ligneApi($data['global']),
                'pap' => $data['pap'],
                'alertes' => $data['alertes'],
                'par_axe' => $this->lignesApi($data['parAxe']),
                'par_categorie' => $this->lignesApi($data['parCategorie']),
                'par_structure' => $this->lignesApi($data['parStructure']),
                'par_bailleur' => [
                    'ceeac_ptf' => $this->lignesApi($data['parBailleur']['ceeac_ptf'] ?? []),
                    'sources' => $this->lignesApi($data['parBailleur']['sources'] ?? []),
                ],
                'evolution' => collect($data['evolution'])->map(fn ($r) => [
                    'mois' => (int) ($r['mois'] ?? 0),
                    'montant' => Montant::api($r['montant'] ?? 0),
                ])->values()->all(),
                'suivi_eval' => [
                    'evaluation' => $eval ? [
                        'score_global' => Montant::api($eval->score_global, 1),
                        'niveau' => $eval->niveau,
                        'genere_le' => $eval->genere_le?->toIso8601String(),
                    ] : null,
                    'pap' => [
                        'taux_physique' => (float) ($papSe['taux_physique'] ?? 0),
                        'taux_financier' => (float) ($papSe['taux_financier'] ?? 0),
                    ],
                    'feux' => $this->tableauBord->feuxIndicateurs($exercice->id),
                ],
                'retards' => [
                    'synthese' => [
                        'total' => $retardSynthese['total'],
                        'montant' => Montant::api($retardSynthese['montant']),
                        'taux_moyen' => $retardSynthese['taux_moyen'],
                    ],
                    'filtres' => $filtres,
                    'options' => $this->optionsRetard($exercice->id),
                    'lignes' => collect($retards->items())->map(fn (Activite $a) => $this->formatRetard($a))->values()->all(),
                    'meta' => [
                        'total' => $retards->total(),
                        'current_page' => $retards->currentPage(),
                        'last_page' => $retards->lastPage(),
                    ],
                    'href_export_pdf' => $this->href('reporting.cockpit.retard.export', 'pdf').'?'.http_build_query($qs),
                    'href_export_xlsx' => $this->href('reporting.cockpit.retard.export', 'xlsx').'?'.http_build_query($qs),
                    'href_export_csv' => $this->href('reporting.cockpit.retard.export', 'csv').'?'.http_build_query($qs),
                ],
            ],
        ]);
    }

    public function planification(Request $request): JsonResponse
    {
        $this->assertReporting($request);
        abort_unless($this->autorisePlanification($request), 403);

        $periodiques = Rapport::with('generePar')
            ->whereIn('type', ['trimestriel', 'semestriel', 'annuel', 'ipsas_etat_financier'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Rapport $r) => $this->formatRapport($r))
            ->values()
            ->all();

        $audit = AuditReporting::with(['user', 'rapport'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AuditReporting $a) => [
                'action' => $a->action,
                'rapport' => $a->rapport?->reference ?? $a->type_rapport,
                'utilisateur' => $a->user?->name,
                'ip' => $a->ip,
                'le' => $a->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'schedule' => config('reporting.schedule'),
                'periodiques' => $periodiques,
                'audit' => $audit,
            ],
        ]);
    }

    public function liveSnapshot(Request $request): JsonResponse
    {
        abort_unless($this->autoriseCockpit($request), 403);
        $exercice = $this->exerciceObligatoire($request);

        return response()->json(['data' => $this->live->snapshot($exercice->id)]);
    }

    public function liveDrilldown(Request $request): JsonResponse
    {
        abort_unless($this->autoriseCockpit($request), 403);
        $validated = $request->validate([
            'exercice' => ['nullable', 'integer', 'exists:exercices,id'],
            'niveau' => ['required', 'string', 'in:categorie,axe,structure,pilier'],
            'parent' => ['required', 'string', 'max:120'],
        ]);
        $exercice = $this->exerciceObligatoire($request);

        return response()->json([
            'data' => $this->live->drilldown($exercice->id, $validated['niveau'], $validated['parent']),
        ]);
    }

    public function livePing(Request $request): JsonResponse
    {
        abort_unless($this->autoriseCockpit($request), 403);

        return response()->json(['data' => $this->live->ping((int) $request->integer('since', 0))]);
    }

    public function catalogue(Request $request): JsonResource
    {
        $this->assertReporting($request);

        $categories = RapportCategorie::with(['modeles' => fn ($q) => $q->where('actif', true)->orderBy('titre')])
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(fn (RapportCategorie $c) => [
                'code' => $c->code,
                'libelle' => $c->libelle,
                'modeles' => $c->modeles->map(fn (RapportModele $m) => [
                    'type' => $m->type,
                    'titre' => $m->titre,
                    'description' => $m->description,
                    'parametres_schema' => $m->parametres_schema,
                ])->values(),
            ]);

        $this->audit->journaliser('api_catalogue', null, $request->user(), $request);

        return new JsonResource(['categories' => $categories]);
    }

    public function rapports(Request $request): JsonResource
    {
        $this->assertReporting($request);

        $query = Rapport::with(['exercice', 'generePar'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('exercice'), function ($q) use ($request) {
                $ex = Exercice::find($request->integer('exercice'))
                    ?? Exercice::firstWhere('annee', $request->integer('exercice'));
                if ($ex) {
                    $q->where('exercice_id', $ex->id);
                }
            })
            ->latest();

        $paginated = $query->paginate(min(50, max(1, (int) $request->integer('per_page', $request->integer('limit', 20)))));

        $this->audit->journaliser('api_liste', null, $request->user(), $request, [
            'total' => $paginated->total(),
        ]);

        return JsonResource::collection($paginated->through(fn (Rapport $r) => $this->formatRapport($r)));
    }

    public function show(Request $request, Rapport $rapport): JsonResource
    {
        $this->assertReporting($request);

        $rapport->load(['exercice', 'generePar', 'validePar', 'publiePar', 'modele.categorie', 'ipsas']);

        $this->audit->journaliser('api_consult', $rapport, $request->user(), $request);

        return new JsonResource(array_merge($this->formatRapport($rapport), [
            'statut_workflow' => $rapport->statut_workflow,
            'verrouille' => $rapport->verrouille,
            'ged_indexe' => $rapport->ged_indexe,
            'checksum_sources' => $rapport->checksum_sources,
            'hash' => $rapport->hash,
            'verification_url' => $rapport->urlVerification(),
            'categorie' => $rapport->modele?->categorie?->code,
            'ipsas' => $rapport->ipsas ? [
                'reference_note' => $rapport->ipsas->reference_note,
                'donnees_preparatoires' => $rapport->ipsas->donnees_preparatoires,
            ] : null,
            'href_telechargement' => $rapport->chemin ? $this->href('reporting.telecharger', $rapport) : null,
        ]));
    }

    public function types(Request $request): JsonResource
    {
        $this->assertReporting($request);

        return new JsonResource([
            'types' => collect(RapportComposer::TYPES)->map(fn ($titre, $type) => [
                'type' => $type,
                'titre' => $titre,
            ])->values(),
        ]);
    }

    private function assertReporting(Request $request): void
    {
        abort_unless($this->peut($request, 'reporting.view') || $this->peut($request, 'rapport.voir'), 403);
    }

    private function autoriseCockpit(Request $request): bool
    {
        return $this->peut($request, 'tableau_bord.voir')
            || $this->peut($request, 'reporting.view')
            || $this->peut($request, 'rapport.voir');
    }

    private function autorisePlanification(Request $request): bool
    {
        return $this->peut($request, 'reporting.admin') || $this->peut($request, 'reporting.generate');
    }

    private function peut(Request $request, string $ability): bool
    {
        try {
            return (bool) $request->user()?->can($ability);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function formatRapport(Rapport $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'type' => $r->type,
            'titre' => $r->titre,
            'titre_catalogue' => RapportComposer::TYPES[$r->type] ?? $r->type,
            'exercice' => $r->exercice?->annee,
            'periode' => $r->periode,
            'format' => $r->format,
            'version' => $r->version,
            'statut' => $r->statut,
            'statut_workflow' => $r->statut_workflow,
            'genere_par' => $r->generePar?->name,
            'genere_le' => $r->created_at?->toIso8601String(),
            'href_telechargement' => $r->chemin ? $this->href('reporting.telecharger', $r) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function formatRetard(Activite $a): array
    {
        $jours = $this->retards->joursRetard($a);

        return [
            'id' => $a->id,
            'code' => $a->code,
            'intitule' => $a->intitule,
            'structure' => $a->structure?->libelle,
            'responsable' => $a->responsable?->name,
            'date_fin_prevue' => $a->date_fin_prevue,
            'taux_physique' => (float) $a->taux_realisation_physique,
            'montant_total' => Montant::api($a->montant_total),
            'jours_retard' => $jours,
            'niveau' => $this->retards->niveau($jours),
            'href_fiche' => $this->href('reporting.cockpit.activite', $a),
        ];
    }

    /** @return array<string, mixed> */
    private function optionsRetard(int $exerciceId): array
    {
        $opts = $this->retards->optionsFiltres($exerciceId);

        return [
            'structures' => $opts['structures']->map(fn ($s) => $s->only(['id', 'libelle']))->values()->all(),
            'responsables' => $opts['responsables']->map(fn ($u) => $u->only(['id', 'name']))->values()->all(),
            'piliers' => $opts['piliers']->map(fn ($p) => $p->only(['id', 'code', 'intitule']))->values()->all(),
            'axes' => $opts['axes']->map(fn ($a) => $a->only(['id', 'pilier_id', 'code', 'intitule']))->values()->all(),
            'sources' => $opts['sources']->map(fn ($s) => $s->only(['id', 'libelle']))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function filtresRetard(Request $request): array
    {
        return array_filter([
            'structure_id' => $request->integer('structure_id') ?: null,
            'responsable_id' => $request->integer('responsable_id') ?: null,
            'pilier_id' => $request->integer('pilier_id') ?: null,
            'axe_id' => $request->integer('axe_id') ?: null,
            'source_id' => $request->integer('source_id') ?: null,
            'du' => $request->date('du')?->toDateString(),
            'au' => $request->date('au')?->toDateString(),
        ]);
    }

    /** @param  array<string, mixed>  $ligne */
    private function ligneApi(array $ligne): array
    {
        return Montant::champs($ligne, self::MONTANTS);
    }

    /** @param  iterable<int, array<string, mixed>>  $lignes */
    private function lignesApi(iterable $lignes): array
    {
        return Collection::make($lignes)
            ->map(fn ($r) => $this->ligneApi(is_array($r) ? $r : (array) $r))
            ->values()
            ->all();
    }

    private function exercice(Request $request): ?Exercice
    {
        if ($request->filled('exercice')) {
            $opt = $request->input('exercice');

            return Exercice::where('id', $opt)->orWhere('annee', $opt)->first();
        }

        return Exercice::firstWhere('statut', 'ouvert') ?? Exercice::orderByDesc('annee')->first();
    }

    private function exerciceObligatoire(Request $request): Exercice
    {
        $exercice = $this->exercice($request);
        abort_unless($exercice, 422, 'Exercice introuvable.');

        return $exercice;
    }

    private function href(string $name, mixed ...$params): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, $params, false);
    }
}
