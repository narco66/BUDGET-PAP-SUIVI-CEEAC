<?php

namespace App\Http\Controllers\Reporting;

use App\Exports\Reporting\ActivitesRetardExport;
use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\AuditReporting;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\Structure;
use App\Services\Reporting\ActivitesRetardService;
use App\Services\Reporting\RapportAuditService;
use App\Services\Reporting\RapportComposer;
use App\Services\Reporting\RapportPeriodeService;
use App\Services\Reporting\RapportRenderer;
use App\Jobs\GenererRapportJob;
use App\Services\Reporting\ReportingAsyncPolicy;
use App\Services\Reporting\ReportingService;
use App\Services\Reporting\RapportWorkflowService;
use App\Services\WorkflowService;
use App\Services\Reporting\SignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportingController extends Controller
{
    public function __construct(
        private readonly ReportingService $svc,
        private readonly RapportComposer $composer,
        private readonly RapportRenderer $renderer,
        private readonly RapportPeriodeService $periodes,
        private readonly SignatureService $signature,
        private readonly ActivitesRetardService $retards,
        private readonly RapportWorkflowService $workflow,
        private readonly WorkflowService $d14,
        private readonly RapportAuditService $audit,
    ) {
    }

    /** Hub « Reporting & Analyse ». */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Rapport::class);

        return view('reporting.index', [
            'types' => RapportComposer::TYPES,
            'exercice' => $this->exercice($request),
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'recents' => Rapport::with('generePar')->latest()->limit(8)->get(),
        ]);
    }

    /** Cockpit décisionnel exécutif. */
    public function cockpit(Request $request): View
    {
        abort_unless($request->user()->can('tableau_bord.voir') || $request->user()->can('reporting.view'), 403);
        $exercice = $this->exercice($request);
        $filtres = $this->filtresRetard($request);

        $tb = app(\App\Services\TableauBordService::class);

        $persona = \App\Support\PersonaTableauxBord::pour($request->user());

        $data = $this->svc->cockpit($exercice->id);
        if ($persona['persona_key'] === 'executif') {
            $ordre = ['danger' => 0, 'warning' => 1, 'success' => 2];
            usort($data['alertes'], fn ($a, $b) => ($ordre[$a['niveau']] ?? 9) <=> ($ordre[$b['niveau']] ?? 9));
        }

        return view('reporting.cockpit', [
            'persona' => $persona,
            'reverb' => \App\Support\ReverbClientConfig::pourFront(),
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'data' => $data,
            // Bande Suivi-Évaluation : relie pilotage budgétaire et performance dans une vue unique.
            'suiviEval' => [
                'evaluation' => \App\Models\EvaluationPeriodique::where('exercice_id', $exercice->id)
                    ->orderByDesc('genere_le')->first(),
                'pap' => $tb->executionPap($exercice->id),
                'feux' => $tb->feuxIndicateurs($exercice->id),
            ],
            'retards' => $this->retards->paginer($exercice->id, $filtres),
            'retardSynthese' => $this->retards->synthese($exercice->id, $filtres),
            'retardOptions' => $this->retards->optionsFiltres($exercice->id),
            'retardFiltres' => $filtres,
            'retardSvc' => $this->retards,
        ]);
    }

    /** Export des activités en retard (PDF / Excel / CSV), filtres repris. */
    public function activitesRetardExport(Request $request, string $format): Response
    {
        abort_unless($request->user()->can('tableau_bord.voir') || $request->user()->can('reporting.view'), 403);
        $exercice = $this->exercice($request);
        $filtres = $this->filtresRetard($request);
        $activites = $this->retards->collection($exercice->id, $filtres);
        $nom = 'activites-retard-'.$exercice->annee.'-'.now()->format('Ymd');

        if ($format === 'xlsx') {
            return Excel::download(new ActivitesRetardExport($activites, $this->retards), $nom.'.xlsx');
        }
        if ($format === 'csv') {
            return Excel::download(new ActivitesRetardExport($activites, $this->retards), $nom.'.csv', ExcelFormat::CSV);
        }

        $pdf = Pdf::loadView('reporting.pdf.activites-retard', [
            'exercice' => $exercice,
            'activites' => $activites,
            'synthese' => $this->retards->synthese($exercice->id, $filtres),
            'svc' => $this->retards,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($nom.'.pdf');
    }

    /** Fiche détaillée d'une activité (accès depuis le cockpit). */
    public function activiteDetail(Request $request, Activite $activite): View
    {
        abort_unless($request->user()->can('tableau_bord.voir') || $request->user()->can('reporting.view') || $request->user()->can('rbm.voir'), 403);

        $activite->load([
            'produit.axe.pilier', 'sousProduit', 'structure.parent', 'responsable',
            'ligneBudgetaire.sourceFinancement', 'taches.responsable',
            'indicateurs.mesures', 'gedDocuments', 'journaux.user',
        ]);

        return view('reporting.activite-detail', [
            'activite' => $activite,
            'svc' => $this->retards,
        ]);
    }

    /** Page de configuration / aperçu d'un rapport donné. */
    public function show(Request $request, string $type): View
    {
        $this->authorize('viewAny', Rapport::class);
        abort_unless(array_key_exists($type, RapportComposer::TYPES), 404);
        $this->authorizeRapportTechnique($request, $type);
        $exercice = $this->exercice($request);

        return view('reporting.show', [
            'type' => $type,
            'titre' => $this->composer->titre($type),
            'exercice' => $exercice,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::actif()->orderBy('libelle')->get(),
            'periodeOptions' => $this->periodes->optionsPourTypeRapport($exercice->annee, $type),
            'periodeCourante' => $this->resoudrePeriode($exercice, $request)['periode'],
            'apercu' => $this->composer->composer($type, $exercice, $this->params($request, $exercice)),
        ]);
    }

    /** Planification automatique et journal audit (P3). */
    public function planification(Request $request): View
    {
        $this->authorize('viewAny', Rapport::class);
        abort_unless($request->user()->can('reporting.admin') || $request->user()->can('reporting.generate'), 403);

        return view('reporting.planification', [
            'schedule' => config('reporting.schedule'),
            'derniersPeriodiques' => Rapport::with('generePar')
                ->whereIn('type', ['trimestriel', 'semestriel', 'annuel', 'ipsas_etat_financier'])
                ->latest()->limit(12)->get(),
            'auditRecents' => AuditReporting::with(['user', 'rapport'])->latest('created_at')->limit(20)->get(),
        ]);
    }

    /** Génère et télécharge un rapport (PDF / Excel / Word / CSV), avec historisation. */
    public function generer(Request $request, string $type): Response|RedirectResponse
    {
        $this->authorize('generate', Rapport::class);
        abort_unless(array_key_exists($type, RapportComposer::TYPES), 404);
        $this->authorizeRapportTechnique($request, $type);

        $format = $request->string('format', 'pdf')->toString();
        $exercice = $this->exercice($request);
        $this->resoudrePeriode($exercice, $request);

        if (ReportingAsyncPolicy::doitAsync($type, $format)) {
            return $this->dispatchGeneration($request, $type, $format, $exercice);
        }

        $resultat = $this->renderer->generer($type, $exercice, $format, $this->params($request, $exercice), $request->user());

        return response($resultat['contenu'], 200, [
            'Content-Type' => $resultat['mime'],
            'Content-Disposition' => 'attachment; filename="'.$resultat['filename'].'"',
        ]);
    }

    /** Génère un rapport en arrière-plan — disponible au Centre d'exports. */
    public function genererAsync(Request $request, string $type): RedirectResponse
    {
        $this->authorize('generate', Rapport::class);
        abort_unless(array_key_exists($type, RapportComposer::TYPES), 404);
        $this->authorizeRapportTechnique($request, $type);

        $format = $request->string('format', 'pdf')->toString();
        abort_unless(in_array($format, ['pdf', 'excel', 'word', 'csv', 'pptx'], true), 422, "Format non supporté : {$format}");

        $exercice = $this->exercice($request);

        return $this->dispatchGeneration($request, $type, $format, $exercice);
    }

    private function dispatchGeneration(Request $request, string $type, string $format, Exercice $exercice): RedirectResponse
    {
        $this->resoudrePeriode($exercice, $request);

        GenererRapportJob::dispatch(
            $type,
            $exercice->id,
            $format,
            $this->params($request, $exercice),
            $request->user()->id,
        );

        return back()->with('succes', 'Génération lancée en arrière-plan — le rapport sera disponible au Centre d’exports dès qu’il sera prêt.');
    }

    /** Centre d'exports : historique des rapports générés. */
    public function historique(Request $request): View
    {
        $this->authorize('viewAny', Rapport::class);

        $rapports = Rapport::with(['generePar', 'signePar', 'validePar', 'publiePar', 'exercice'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('format'), fn ($q) => $q->where('format', $request->format))
            ->when($request->filled('periode'), fn ($q) => $q->where('periode', $request->periode))
            ->latest()->paginate(20)->withQueryString();

        return view('reporting.historique', [
            'rapports' => $rapports,
            'types' => RapportComposer::TYPES,
        ]);
    }

    /** Re-téléchargement d'un rapport archivé. */
    public function telecharger(Request $request, Rapport $rapport): Response
    {
        $this->authorize('download', $rapport);
        abort_unless($rapport->chemin && Storage::disk('local')->exists($rapport->chemin), 404, 'Fichier archivé introuvable.');

        $this->audit->journaliser('download', $rapport, $request->user(), $request);

        $ext = pathinfo($rapport->chemin, PATHINFO_EXTENSION);

        return response(Storage::disk('local')->get($rapport->chemin), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($rapport->chemin) ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$rapport->reference.'.'.$ext.'"',
        ]);
    }

    /** Signature électronique d'un rapport (verrouille le document). */
    public function signer(Request $request, Rapport $rapport): RedirectResponse
    {
        $this->authorize('sign', $rapport);

        if ($rapport->estSigne()) {
            return back()->with('erreur', 'Ce rapport est déjà signé.');
        }

        $rapport->signature = $this->signature->signer($rapport, $request->user());
        $rapport->signe_par = $request->user()->id;
        $rapport->signe_le = now();
        $rapport->statut = 'signe';
        $rapport->verrouille = true;
        $rapport->save();

        $this->audit->journaliser('sign', $rapport, $request->user(), $request);

        return back()->with('succes', "Rapport {$rapport->reference} signé et verrouillé.");
    }

    /** Validation MOA (étape intermédiaire avant publication). */
    public function valider(Request $request, Rapport $rapport): RedirectResponse
    {
        $this->authorize('validate', $rapport);

        try {
            $rapport = $rapport->fresh();
            if ($rapport->statut_workflow !== 'genere') {
                throw new \RuntimeException('Seul un rapport généré peut être validé.');
            }

            if (! $rapport->workflowActif) {
                $this->d14->demarrer($rapport, 'RAPPORT', $request->user());
            }

            $instance = $rapport->fresh()->workflowActif;
            if ($instance && $this->d14->peutAgir($instance, $request->user())) {
                $this->d14->traiterDecision($instance, $request->user(), 'valider');
            } else {
                $this->workflow->valider($rapport, $request->user());
            }
        } catch (\RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $this->audit->journaliser('validate', $rapport->fresh(), $request->user(), $request);

        return back()->with('succes', "Rapport {$rapport->reference} validé MOA.");
    }

    /** Publication institutionnelle (verrouille et indexe GED si besoin). */
    public function publier(Request $request, Rapport $rapport): RedirectResponse
    {
        $this->authorize('publish', $rapport);

        try {
            $rapport = $rapport->fresh();
            $instance = $rapport->workflowActif;

            if ($instance && $this->d14->peutAgir($instance, $request->user())) {
                $this->d14->traiterDecision($instance, $request->user(), 'valider');
            } else {
                $this->workflow->publier($rapport, $request->user());
            }
        } catch (\RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $this->audit->journaliser('publish', $rapport->fresh(), $request->user(), $request);

        return back()->with('succes', "Rapport {$rapport->reference} publié et verrouillé.");
    }

    /** Filtres avancés des activités en retard (repris dans les exports). */
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

    /** @return array<string, mixed> */
    private function params(Request $request, Exercice $exercice): array
    {
        $periode = $this->resoudrePeriode($exercice, $request);

        return array_filter([
            'structure_id' => $request->integer('structure_id') ?: null,
            'periode' => $periode['periode'],
            'periode_type' => $periode['periode_type'],
            'periode_debut' => $periode['periode_debut'],
            'periode_fin' => $periode['periode_fin'],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @return array{periode:string, periode_type:string, periode_debut:string, periode_fin:string} */
    private function resoudrePeriode(Exercice $exercice, Request $request): array
    {
        try {
            return $this->periodes->resoudre($exercice, $request->input('periode'));
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }

    private function exercice(Request $request): Exercice
    {
        abort_unless($request->filled('exercice') || Exercice::query()->exists(), 422, 'Exercice budgétaire requis.');

        return $request->filled('exercice')
            ? Exercice::findOrFail($request->integer('exercice'))
            : (Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->firstOrFail());
    }

    private function authorizeRapportTechnique(Request $request, string $type): void
    {
        if (! str_starts_with($type, 'technique_')) {
            return;
        }

        abort_unless(
            $request->user()->can('reporting.admin') || $request->user()->can('admin_technique.view'),
            403,
            'Réservé aux administrateurs reporting ou technique.'
        );
    }
}
