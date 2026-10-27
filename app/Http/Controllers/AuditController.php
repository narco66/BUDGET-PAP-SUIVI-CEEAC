<?php

namespace App\Http\Controllers;

use App\Models\AuditControleIntegrite;
use App\Models\AuditExport;
use App\Models\AuditScellement;
use App\Models\JournalAudit;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\Audit\AuditAccesService;
use App\Services\Audit\AuditIntegriteService;
use App\Services\Audit\AuditRechercheService;
use App\Support\AuditLogger;
use App\Support\Horodatage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Module Journalisation, Traçabilité et Horodatage : consultation cloisonnée du journal central. */
class AuditController extends Controller
{
    public function __construct(
        private readonly AuditAccesService $acces,
        private readonly AuditRechercheService $recherche,
    ) {}

    public function index(Request $request): View
    {
        $this->autoriser($request, 'audit.events.view_own');
        $filtres = $request->only(AuditRechercheService::FILTRES);

        $journaux = $this->recherche->requete($request->user(), $filtres)
            ->orderByDesc('id')->paginate(40)->withQueryString();

        return view('audit.index', [
            'journaux' => $journaux,
            'filtres' => $filtres,
            'perimetre' => $this->acces->perimetre($request->user()),
            'options' => $this->options($request->user()),
        ]);
    }

    public function tableauDeBord(Request $request): View
    {
        $this->autoriser($request, 'audit.dashboard.view');

        return view('audit.tableau-de-bord', ['stats' => $this->recherche->tableauDeBord($request->user(), $request->input('depuis'))]);
    }

    public function show(Request $request, JournalAudit $journal): View
    {
        $this->autoriser($request, 'audit.events.view_own');
        abort_unless($this->acces->peutConsulter($journal, $request->user()), 403, 'Événement hors de votre périmètre de consultation.');

        $scellement = AuditScellement::query()->where('de_id', '<=', $journal->id)->where('a_id', '>=', $journal->id)->first();
        $correles = $journal->correlation_id
            ? $this->acces->filtrer(JournalAudit::query(), $request->user())->where('correlation_id', $journal->correlation_id)->orderBy('id')->limit(200)->get()
            : collect();

        return view('audit.show', [
            'j' => $journal->load('user', 'titulaire', 'structure'),
            'scellement' => $scellement,
            'correles' => $correles,
            'empreinteValide' => $journal->empreinteValide(),
        ]);
    }

    public function securite(Request $request): View
    {
        $this->autoriser($request, 'audit.security.view');
        $filtres = $request->only(AuditRechercheService::FILTRES);

        return view('audit.securite', [
            'journaux' => $this->recherche->requete($request->user(), ['categorie' => 'securite'] + $filtres)->orderByDesc('id')->paginate(30, ['*'], 'evenements')->withQueryString(),
            'connexions' => LoginLog::query()->with('user')
                ->when($request->filled('acteur'), fn ($q) => $q->where('email', 'like', '%'.$request->acteur.'%'))
                ->latest('created_at')->paginate(20, ['*'], 'connexions')->withQueryString(),
            'echecs24h' => LoginLog::query()->where('succes', false)->where('created_at', '>=', now()->subDay())->count(),
            'refus24h' => JournalAudit::query()->where('action', 'acces_refuse')->where('created_at', '>=', now()->subDay())->count(),
            'filtres' => $filtres,
        ]);
    }

    public function dossier(Request $request, string $type, int $id): View
    {
        $this->autoriser($request, 'audit.events.view_own');
        abort_unless(preg_match('/^[A-Za-z\\\\]+$/', $type) === 1, 404);

        $chronologie = $this->recherche->chronologieDossier($request->user(), $type, $id);
        abort_if($chronologie->isEmpty() && ! $this->acces->etendu($request->user()), 403, 'Dossier hors de votre périmètre de consultation.');

        return view('audit.dossier', ['type' => class_basename($type), 'id' => $id, 'chronologie' => $chronologie]);
    }

    public function utilisateur(Request $request, User $user): View
    {
        $this->autoriser($request, 'audit.events.view_own');
        $soi = $user->id === $request->user()->id;
        abort_unless($soi || $this->acces->etendu($request->user()) || $this->acces->unites($request->user())->contains((int) $user->structure_id), 403);

        return view('audit.utilisateur', [
            'cible' => $user,
            'journaux' => $this->recherche->requete($request->user(), ['acteur' => $user->id] + $request->only(AuditRechercheService::FILTRES))
                ->orderByDesc('id')->paginate(40)->withQueryString(),
        ]);
    }

    public function integrite(Request $request): View
    {
        $this->autoriser($request, 'audit.integrity.view');

        return view('audit.integrite', [
            'controles' => AuditControleIntegrite::query()->latest('id')->limit(20)->get(),
            'scellements' => AuditScellement::query()->latest('a_id')->limit(20)->get(),
            'nonScelles' => JournalAudit::query()->where('id', '>', (int) AuditScellement::query()->max('a_id'))->count(),
            'sansEmpreinte' => JournalAudit::query()->whereNull('empreinte')->count(),
            'total' => JournalAudit::query()->count(),
            'peutExecuter' => $request->user()->can('audit.integrity.execute'),
        ]);
    }

    public function verifierIntegrite(Request $request, AuditIntegriteService $integrite): RedirectResponse
    {
        $this->autoriser($request, 'audit.integrity.execute');
        $c = $integrite->verifier($request->user());

        return back()->with($c->resultat === 'anomalie' ? 'erreur' : 'succes', "Contrôle {$c->reference} : {$c->resultat} ({$c->evenements_verifies} événements, {$c->scellements_verifies} scellements).");
    }

    public function sceller(Request $request, AuditIntegriteService $integrite): RedirectResponse
    {
        $this->autoriser($request, 'audit.integrity.execute');
        $s = $integrite->sceller($request->user());

        return back()->with('succes', $s ? "Scellement {$s->reference} : {$s->nombre} événement(s)." : 'Aucun événement à sceller (délai de stabilisation non écoulé).');
    }

    /** Export CSV ou JSON du périmètre filtré, tracé (JNL-012, JNL-013). */
    public function export(Request $request, string $format): StreamedResponse
    {
        $this->autoriser($request, 'audit.exports.download');
        abort_unless(in_array($format, ['csv', 'json'], true), 404);
        $user = $request->user();
        $filtres = $request->only(AuditRechercheService::FILTRES);
        $lignes = $this->recherche->requete($user, $filtres)->orderBy('id')->limit(50000)->get();

        $colonnes = ['id', 'uuid', 'horodatage_utc', 'horodatage_libreville', 'categorie', 'module', 'code_evenement', 'action', 'resultat', 'gravite',
            'acteur', 'role', 'pour_compte_de', 'objet', 'reference', 'correlation_id', 'request_id', 'motif', 'ip', 'empreinte'];
        $donnees = $lignes->map(fn (JournalAudit $j) => array_combine($colonnes, [
            $j->id, $j->uuid, Horodatage::utc($j->instant()), Horodatage::local($j->instant(), 'Y-m-d H:i:s.u'), $j->categorie, $j->module,
            $j->code_evenement, $j->action, $j->resultat, $j->gravite, $j->user_nom, $j->role, $j->pour_compte_de,
            $j->auditable_type ? class_basename($j->auditable_type).'#'.$j->auditable_id : null, $j->reference_objet,
            $j->correlation_id, $j->request_id, $j->motif, $j->ip, $j->empreinte,
        ]));

        $contenu = $format === 'json'
            ? json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            : $this->csv($colonnes, $donnees->all());
        $empreinte = hash('sha256', $contenu);

        $export = DB::transaction(function () use ($user, $format, $filtres, $lignes, $empreinte) {
            $export = AuditExport::create([
                'reference' => \App\Services\Audit\AuditIntegriteService::reference('EXP'),
                'user_id' => $user->id,
                'format' => $format,
                'perimetre' => $this->acces->perimetre($user),
                'filtres' => $filtres ?: null,
                'nombre' => $lignes->count(),
                'empreinte' => $empreinte,
            ]);
            AuditLogger::evenement('audit_export', $export, [
                'reference' => $export->reference,
                'apres' => ['format' => $format, 'nombre' => $lignes->count(), 'filtres' => $filtres, 'empreinte' => $empreinte],
            ]);

            return $export;
        });

        return response()->streamDownload(fn () => print ($contenu), "journal-audit-{$export->reference}.{$format}", [
            'Content-Type' => $format === 'json' ? 'application/json' : 'text/csv; charset=UTF-8',
            'X-Export-Reference' => $export->reference,
            'X-Export-Sha256' => $empreinte,
        ]);
    }

    private function csv(array $colonnes, array $lignes): string
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, $colonnes, ';');
        foreach ($lignes as $l) {
            fputcsv($f, array_values($l), ';');
        }
        rewind($f);

        return (string) stream_get_contents($f);
    }

    private function options(User $user): array
    {
        $base = fn () => $this->acces->filtrer(JournalAudit::query(), $user);

        return [
            'modules' => $base()->whereNotNull('module')->distinct()->pluck('module')->merge(AuditLogger::modulesConnus())->unique()->sort()->values(),
            'actions' => $base()->distinct()->orderBy('action')->limit(300)->pluck('action'),
            'categories' => JournalAudit::CATEGORIES,
            'gravites' => JournalAudit::GRAVITES,
            'resultats' => JournalAudit::RESULTATS,
        ];
    }

    private function autoriser(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
