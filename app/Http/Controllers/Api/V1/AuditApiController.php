<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\AuditControleIntegrite;
use App\Models\AuditExport;
use App\Models\JournalAudit;
use App\Models\User;
use App\Services\Audit\AuditAccesService;
use App\Services\Audit\AuditAlerteService;
use App\Services\Audit\AuditIntegriteService;
use App\Services\Audit\AuditRechercheService;
use App\Support\AuditLogger;
use App\Support\Horodatage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * API REST du journal (§ 12.3). Lecture cloisonnée côté serveur ; aucune route ne permet de créer,
 * modifier ou supprimer un événement d'audit : seuls les services internes écrivent au journal.
 */
class AuditApiController extends Controller
{
    public function __construct(
        private readonly AuditAccesService $acces,
        private readonly AuditRechercheService $recherche,
    ) {}

    public function events(Request $request): JsonResponse
    {
        $this->autoriser($request, 'audit.events.view_own');
        $page = $this->recherche->requete($request->user(), $request->only(AuditRechercheService::FILTRES))
            ->orderByDesc('id')->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn (JournalAudit $j) => $this->evenement($j, false)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'perimetre' => $this->acces->perimetre($request->user())],
        ]);
    }

    public function event(Request $request, JournalAudit $journal): JsonResponse
    {
        $this->autoriser($request, 'audit.events.view_own');
        abort_unless($this->acces->peutConsulter($journal, $request->user()), 403, 'Événement hors de votre périmètre.');

        return response()->json(['data' => $this->evenement($journal, true)]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->autoriser($request, 'audit.dashboard.view');
        $stats = $this->recherche->tableauDeBord($request->user(), $request->input('depuis'));
        $stats['derniers'] = $stats['derniers']->map(fn (JournalAudit $j) => $this->evenement($j, false));
        $stats['dernier_controle'] = $stats['dernier_controle']?->only(['reference', 'resultat', 'debut_le']);
        $stats['dernier_scellement'] = $stats['dernier_scellement']?->only(['reference', 'a_id', 'scelle_le']);

        return response()->json(['data' => $stats]);
    }

    public function subject(Request $request, string $type, int $id): JsonResponse
    {
        $this->autoriser($request, 'audit.events.view_own');
        $chronologie = $this->recherche->chronologieDossier($request->user(), $type, $id);
        abort_if($chronologie->isEmpty() && ! $this->acces->etendu($request->user()), 403, 'Dossier hors de votre périmètre.');

        return response()->json(['data' => $chronologie->map(fn ($e) => ['instant_utc' => Horodatage::utc($e['instant']), 'instant_local' => Horodatage::local($e['instant'])] + $e)->values()]);
    }

    public function userEvents(Request $request, User $user): JsonResponse
    {
        $this->autoriser($request, 'audit.events.view_own');
        abort_unless($user->id === $request->user()->id || $this->acces->etendu($request->user())
            || $this->acces->unites($request->user())->contains((int) $user->structure_id), 403);
        $request->merge(['acteur' => $user->id]);

        return $this->events($request);
    }

    public function security(Request $request): JsonResponse
    {
        $this->autoriser($request, 'audit.security.view');
        $request->merge(['categorie' => 'securite']);

        return $this->events($request);
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->autoriser($request, 'audit.alerts.view');
        $alertes = Alerte::query()->where('module', 'audit')
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')->paginate(25);

        return response()->json(['data' => $alertes->items(), 'meta' => ['total' => $alertes->total(), 'transitions' => AuditAlerteService::TRANSITIONS]]);
    }

    public function alertDecision(Request $request, Alerte $alerte, AuditAlerteService $service): JsonResponse
    {
        $this->autoriser($request, 'audit.alerts.manage');
        $data = $request->validate(['decision' => 'required|string', 'commentaire' => 'nullable|string|max:2000']);
        try {
            return response()->json(['data' => $service->decider($alerte, $request->user(), $data['decision'], $data['commentaire'] ?? null)]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function runIntegrityCheck(Request $request, AuditIntegriteService $integrite): JsonResponse
    {
        $this->autoriser($request, 'audit.integrity.execute');

        return response()->json(['data' => $integrite->verifier($request->user())], 201);
    }

    public function integrityCheck(Request $request, AuditControleIntegrite $controle): JsonResponse
    {
        $this->autoriser($request, 'audit.integrity.view');

        return response()->json(['data' => $controle]);
    }

    /** Export JSON du périmètre filtré, tracé ; le contenu est rendu avec son empreinte. */
    public function createExport(Request $request): JsonResponse
    {
        $this->autoriser($request, 'audit.exports.download');
        $filtres = $request->only(AuditRechercheService::FILTRES);
        $lignes = $this->recherche->requete($request->user(), $filtres)->orderBy('id')->limit(10000)->get()
            ->map(fn (JournalAudit $j) => $this->evenement($j, true));
        $contenu = json_encode($lignes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $export = AuditExport::create([
            'reference' => AuditIntegriteService::reference('EXP'),
            'user_id' => $request->user()->id,
            'format' => 'json',
            'perimetre' => $this->acces->perimetre($request->user()),
            'filtres' => $filtres ?: null,
            'nombre' => $lignes->count(),
            'empreinte' => hash('sha256', $contenu),
        ]);
        AuditLogger::evenement('audit_export', $export, ['reference' => $export->reference, 'apres' => ['format' => 'json', 'nombre' => $lignes->count(), 'filtres' => $filtres]]);

        return response()->json(['data' => ['export' => $export, 'evenements' => $lignes]], 201);
    }

    public function export(Request $request, AuditExport $export): JsonResponse
    {
        $this->autoriser($request, 'audit.exports.download');
        abort_unless($export->user_id === $request->user()->id || $this->acces->etendu($request->user()), 403);

        return response()->json(['data' => $export]);
    }

    private function evenement(JournalAudit $j, bool $detail): array
    {
        $base = [
            'id' => $j->id,
            'uuid' => $j->uuid,
            'code' => $j->code_evenement ?? strtoupper($j->action),
            'categorie' => $j->categorie,
            'module' => $j->module,
            'action' => $j->action,
            'resultat' => $j->resultat,
            'gravite' => $j->gravite,
            'occurred_at' => Horodatage::utc($j->instant()),
            'occurred_at_local' => Horodatage::local($j->instant(), 'Y-m-d H:i:s.v'),
            'fuseau_affichage' => Horodatage::fuseau(),
            'acteur' => ['id' => $j->user_id, 'nom' => $j->user_nom, 'fonction' => $j->role, 'unite_id' => $j->structure_id],
            'pour_compte_de' => $j->pour_compte_de,
            'objet' => $j->auditable_type ? ['type' => class_basename($j->auditable_type), 'id' => $j->auditable_id, 'reference' => $j->reference_objet] : null,
            'correlation_id' => $j->correlation_id,
            'integrite' => $j->empreinteValide() === null ? 'scellement' : ($j->empreinteValide() ? 'conforme' : 'alteree'),
        ];

        return $detail ? $base + [
            'motif' => $j->motif,
            'avant' => $j->avant,
            'apres' => $j->apres,
            // Lignes antérieures au module : recorded_at a reçu l'heure de la migration, il n'a pas de valeur probante.
            'recorded_at' => $j->occurred_at ? Horodatage::utc($j->instantEnregistrement()) : null,
            'effective_at' => Horodatage::utc($j->effective_at),
            'workflow_instance_id' => $j->workflow_instance_id,
            'workflow_tache_id' => $j->workflow_tache_id,
            'request_id' => $j->request_id,
            'source' => $j->source,
            'ip' => $j->ip,
            'empreinte' => $j->empreinte,
        ] : $base;
    }

    private function autoriser(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
