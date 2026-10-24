<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiLog;
use App\Models\ConnectedApp;
use App\Models\Webhook;
use App\Models\WebhookEvent;
use App\Models\WebhookLivraison;
use App\Services\Api\IdempotenceActionService;
use App\Services\Interop\ApiMonitoringService;
use App\Services\Interop\ConnectedAppService;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Portail MOA interopérabilité (session SPA). */
class InteropApiController extends Controller
{
    public function __construct(
        private readonly ConnectedAppService $apps,
        private readonly WebhookService $webhooks,
        private readonly IdempotenceActionService $idempotence,
        private readonly ApiMonitoringService $monitoring,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.view') || $this->peut($request, 'api.admin'), 403);

        return response()->json([
            'stats' => [
                'apps_actives' => ConnectedApp::query()->where('statut', 'actif')->count(),
                'apps_total' => ConnectedApp::query()->count(),
                'clients_actifs' => ApiClient::query()->where('statut', 'actif')->count(),
                'appels_24h' => ApiLog::query()->where('created_at', '>=', now()->subDay())->count(),
            ],
            'peut_admin' => $this->peut($request, 'api.admin'),
            'peut_monitor' => $this->peut($request, 'api.monitor'),
            'peut_publier' => $this->peut($request, 'api.publish'),
            'href_docs' => $this->href('api.docs'),
            'href_inventaire' => ($this->peut($request, 'api.admin') || $this->peut($request, 'api.publish'))
                ? $this->href('admin.interoperabilite.exports.inventaire', 'xlsx')
                : null,
            'href_securite' => ($this->peut($request, 'api.admin') || $this->peut($request, 'api.monitor'))
                ? $this->href('admin.interoperabilite.exports.securite', 'pdf')
                : null,
        ]);
    }

    public function journal(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.monitor') || $this->peut($request, 'api.admin'), 403);

        $logs = ApiLog::query()
            ->with(['user', 'connectedApp'])
            ->when($request->filled('chemin'), fn ($q) => $q->where('chemin', 'like', '%'.$request->chemin.'%'))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', (int) $request->statut))
            ->when($request->filled('statut_metier'), fn ($q) => $q->where('statut_metier', $request->statut_metier))
            ->when($request->filled('connected_app_id'), fn ($q) => $q->where('connected_app_id', $request->connected_app_id))
            ->when($request->filled('depuis'), fn ($q) => $q->whereDate('created_at', '>=', $request->depuis))
            ->latest('created_at')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 40))));

        return response()->json([
            'data' => $logs->getCollection()->map(fn (ApiLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'methode' => $log->methode,
                'chemin' => $log->chemin,
                'statut' => $log->statut,
                'statut_metier' => $log->statut_metier,
                'app' => $log->connectedApp?->code,
                'utilisateur' => $log->user?->name,
                'duree_ms' => $log->duree_ms,
                'ip' => $log->ip,
            ])->values()->all(),
            'meta' => [
                'total' => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
            'apps' => ConnectedApp::query()->orderBy('code')->get(['id', 'code', 'libelle'])->map(fn (ConnectedApp $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'libelle' => $a->libelle,
            ])->values()->all(),
        ]);
    }

    public function monitoring(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.monitor') || $this->peut($request, 'api.admin'), 403);

        $jours = (int) $request->integer('jours', 1);
        $depuis = now()->subDays($jours);
        $kpis = $this->monitoring->kpis($depuis);

        return response()->json([
            'jours' => $jours,
            'kpis' => [
                'appels_total' => (int) $kpis['appels_total'],
                'appels_succes' => (int) $kpis['appels_succes'],
                'appels_erreur' => (int) $kpis['appels_erreur'],
                'appels_rejet' => (int) $kpis['appels_rejet'],
                'latence_moy_ms' => (int) $kpis['latence_moy_ms'],
                'taux_succes' => $kpis['taux_succes'],
            ],
            'top_chemins' => $this->monitoring->topChemins(10, $depuis)->map(fn ($row) => [
                'chemin' => $row->chemin,
                'total' => (int) $row->total,
                'latence_moy' => (int) $row->latence_moy,
            ])->values()->all(),
            'par_application' => $this->monitoring->parApplication($depuis)->map(fn ($row) => [
                'code' => $row->code,
                'total' => (int) $row->total,
            ])->values()->all(),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        return response()->json([
            'types' => config('interoperabilite.app_types', []),
            'scopes' => config('interoperabilite.token_abilities', []),
            'statuts' => ['actif' => 'Actif', 'suspendu' => 'Suspendu', 'revoque' => 'Révoqué'],
        ]);
    }

    public function apps(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        $apps = ConnectedApp::query()
            ->withCount(['clients' => fn ($q) => $q->where('statut', 'actif')])
            ->orderBy('code')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'data' => $apps->getCollection()->map(fn (ConnectedApp $app) => $this->formatApp($app, false))->values()->all(),
            'meta' => [
                'total' => $apps->total(),
                'current_page' => $apps->currentPage(),
                'last_page' => $apps->lastPage(),
            ],
        ]);
    }

    public function storeApp(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $data = $this->validerApp($request);

        $resultat = $this->idempotence->executer($request, 'interop.app.store', function () use ($request, $data) {
            $app = ConnectedApp::query()->create([
                ...$data,
                'created_by' => $request->user()?->id,
                'scopes_autorises' => array_values($data['scopes_autorises']),
            ]);

            return ['status' => 201, 'body' => ['message' => 'Application enregistrée.', 'data' => $this->formatApp($app, false)]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function showApp(Request $request, ConnectedApp $app): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $app->load(['clients' => fn ($q) => $q->latest()]);

        return response()->json(['data' => $this->formatApp($app, true)]);
    }

    public function updateApp(Request $request, ConnectedApp $app): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $data = $this->validerApp($request, $app);
        $data['scopes_autorises'] = array_values($data['scopes_autorises']);
        $app->update($data);

        return response()->json([
            'message' => 'Application mise à jour.',
            'data' => $this->formatApp($app->fresh(), false),
        ]);
    }

    public function suspendreApp(Request $request, ConnectedApp $app): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        $resultat = $this->idempotence->executer($request, 'interop.app.suspendre.'.$app->id, function () use ($request, $app) {
            $this->apps->suspendreApplication($app, $request->user());

            return ['status' => 200, 'body' => ['message' => 'Application suspendue et jetons révoqués.', 'data' => $this->formatApp($app->fresh(), false)]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function reactiverApp(Request $request, ConnectedApp $app): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        $resultat = $this->idempotence->executer($request, 'interop.app.reactiver.'.$app->id, function () use ($app) {
            $this->apps->reactiverApplication($app);

            return ['status' => 200, 'body' => ['message' => 'Application réactivée.', 'data' => $this->formatApp($app->fresh(), false)]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function revoquerJetonsApp(Request $request, ConnectedApp $app): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        $resultat = $this->idempotence->executer($request, 'interop.app.jetons.'.$app->id, function () use ($app) {
            $nb = $this->apps->revoquerJetonsApplication($app);

            return ['status' => 200, 'body' => ['message' => $nb.' jeton(s) révoqué(s).', 'revoques' => $nb]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function storeClient(Request $request, ConnectedApp $app): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $data = $request->validate(['libelle' => ['required', 'string', 'max:100']]);

        $resultat = $this->idempotence->executer($request, 'interop.client.store.'.$app->id, function () use ($app, $data) {
            $created = $this->apps->creerClient($app, $data['libelle']);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Client API créé — conservez le secret affiché, il ne sera plus visible.',
                    'data' => $this->formatClient($created['client']),
                    'client_id' => $created['client_id'],
                    'client_secret' => $created['client_secret'],
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function revoquerClient(Request $request, ConnectedApp $app, ApiClient $client): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        abort_unless($client->connected_app_id === $app->id, 404);

        $resultat = $this->idempotence->executer($request, 'interop.client.revoquer.'.$client->id, function () use ($request, $client) {
            $this->apps->revoquerClient($client, $request->user());

            return ['status' => 200, 'body' => ['message' => 'Client API révoqué.', 'data' => $this->formatClient($client->fresh())]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function webhooks(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        $webhooks = Webhook::query()
            ->with('connectedApp')
            ->when($request->filled('evenement'), fn ($q) => $q->where('evenement', $request->evenement))
            ->orderBy('libelle')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'data' => $webhooks->getCollection()->map(fn (Webhook $w) => $this->formatWebhook($w))->values()->all(),
            'meta' => [
                'total' => $webhooks->total(),
                'current_page' => $webhooks->currentPage(),
                'last_page' => $webhooks->lastPage(),
            ],
            'evenements' => $this->evenementsWebhook(),
        ]);
    }

    public function webhookOptions(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        return response()->json([
            'evenements' => $this->evenementsWebhook(),
            'apps' => ConnectedApp::query()->where('statut', 'actif')->orderBy('code')->get(['id', 'code', 'libelle']),
            'methodes' => ['POST', 'PUT'],
        ]);
    }

    public function storeWebhook(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $data = $this->validerWebhook($request);
        $data['secret'] = ($data['secret'] ?? '') ?: Str::random(32);
        $data['actif'] = $request->boolean('actif', true);

        $resultat = $this->idempotence->executer($request, 'interop.webhook.store', function () use ($data) {
            $webhook = Webhook::query()->create($data);

            return ['status' => 201, 'body' => ['message' => 'Webhook enregistré.', 'data' => $this->formatWebhook($webhook->load('connectedApp'))]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function showWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $webhook->load('connectedApp');
        $livraisons = WebhookLivraison::query()
            ->where('webhook_id', $webhook->id)
            ->latest('created_at')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 30))));

        return response()->json([
            'data' => $this->formatWebhook($webhook),
            'livraisons' => $livraisons->getCollection()->map(fn (WebhookLivraison $l) => $this->formatLivraison($l))->values()->all(),
            'meta' => [
                'total' => $livraisons->total(),
                'current_page' => $livraisons->currentPage(),
                'last_page' => $livraisons->lastPage(),
            ],
        ]);
    }

    public function updateWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $data = $this->validerWebhook($request, $webhook);
        $data['actif'] = $request->boolean('actif');
        if (empty($data['secret'])) {
            unset($data['secret']);
        }
        $webhook->update($data);

        return response()->json([
            'message' => 'Webhook mis à jour.',
            'data' => $this->formatWebhook($webhook->fresh('connectedApp')),
        ]);
    }

    public function destroyWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);
        $webhook->delete();

        return response()->json(['message' => 'Webhook supprimé.']);
    }

    public function testerWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        abort_unless($this->peut($request, 'api.admin'), 403);

        $resultat = $this->idempotence->executer($request, 'interop.webhook.tester.'.$webhook->id, function () use ($webhook) {
            $livraison = $this->webhooks->tester($webhook);
            $message = $livraison->succes
                ? 'Test réussi — livraison HTTP OK.'
                : 'Test envoyé — échec HTTP (voir le journal de livraisons).';

            return [
                'status' => 200,
                'body' => [
                    'message' => $message,
                    'succes' => (bool) $livraison->succes,
                    'livraison' => $this->formatLivraison($livraison),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    /** @return array<string, mixed> */
    private function validerWebhook(Request $request, ?Webhook $webhook = null): array
    {
        $codes = WebhookEvent::query()->where('actif', true)->pluck('code')->implode(',');

        return $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'methode' => ['required', 'string', 'in:POST,PUT'],
            'evenement' => ['required', 'string', 'in:'.$codes],
            'connected_app_id' => ['nullable', 'exists:connected_apps,id'],
            'secret' => ['nullable', 'string', 'max:80'],
            'actif' => ['nullable', 'boolean'],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function evenementsWebhook(): array
    {
        return WebhookEvent::query()
            ->where('actif', true)
            ->orderBy('module')
            ->orderBy('code')
            ->get(['code', 'libelle', 'module'])
            ->map(fn (WebhookEvent $e) => [
                'code' => $e->code,
                'libelle' => $e->libelle,
                'module' => $e->module,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function formatWebhook(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'libelle' => $webhook->libelle,
            'evenement' => $webhook->evenement,
            'url' => $webhook->url,
            'methode' => $webhook->methode ?? 'POST',
            'actif' => (bool) $webhook->actif,
            'connected_app_id' => $webhook->connected_app_id,
            'app' => $webhook->connectedApp?->code,
        ];
    }

    /** @return array<string, mixed> */
    private function formatLivraison(WebhookLivraison $livraison): array
    {
        return [
            'id' => $livraison->id,
            'created_at' => $livraison->created_at?->toIso8601String(),
            'statut' => $livraison->statut,
            'statut_http' => $livraison->statut_http,
            'tentatives' => $livraison->tentatives,
            'prochaine_tentative_le' => $livraison->prochaine_tentative_le?->toIso8601String(),
            'erreur' => $livraison->erreur,
            'succes' => (bool) $livraison->succes,
        ];
    }

    /** @return array<string, mixed> */
    private function validerApp(Request $request, ?ConnectedApp $app = null): array
    {
        $scopesList = implode(',', config('interoperabilite.token_abilities', []));
        $types = implode(',', array_keys(config('interoperabilite.app_types', [])));

        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:connected_apps,code'.($app ? ','.$app->id : '')],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', 'in:'.$types],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'scopes_autorises' => ['required', 'array', 'min:1'],
            'scopes_autorises.*' => ['string', 'in:'.$scopesList],
            'statut' => ['required', 'string', 'in:actif,suspendu,revoque'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formatApp(ConnectedApp $app, bool $detail): array
    {
        $row = [
            'id' => $app->id,
            'code' => $app->code,
            'libelle' => $app->libelle,
            'description' => $app->description,
            'type' => $app->type,
            'type_libelle' => config('interoperabilite.app_types.'.$app->type, $app->type),
            'contact_email' => $app->contact_email,
            'scopes_autorises' => $app->scopes_autorises ?? [],
            'statut' => $app->statut,
            'clients_count' => $app->clients_count ?? $app->clients()->where('statut', 'actif')->count(),
            'est_active' => $app->estActive(),
            'est_suspendue' => $app->estSuspendue(),
        ];

        if ($detail) {
            $row['clients'] = $app->clients->map(fn (ApiClient $c) => $this->formatClient($c))->values()->all();
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function formatClient(ApiClient $client): array
    {
        return [
            'id' => $client->id,
            'libelle' => $client->libelle,
            'client_id' => $client->client_id,
            'statut' => $client->statut,
            'actif' => $client->estActif(),
            'derniere_utilisation_le' => $client->derniere_utilisation_le?->toIso8601String(),
        ];
    }

    private function peut(Request $request, string $ability): bool
    {
        try {
            return (bool) $request->user()?->can($ability);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    private function href(string $name, mixed ...$params): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, $params, false);
    }
}
