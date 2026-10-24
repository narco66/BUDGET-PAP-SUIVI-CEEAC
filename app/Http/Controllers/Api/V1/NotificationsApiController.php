<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\EmailTemplate;
use App\Services\Alertes\AlerteDashboardService;
use App\Services\Alertes\AlerteRegistryService;
use App\Services\Api\IdempotenceActionService;
use App\Services\Notifications\AuditNotificationService;
use App\Services\Notifications\NotificationInboxService;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** API REST Notifications & Alertes (N-P3 + SPA). */
class NotificationsApiController extends Controller
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
        private readonly AlerteDashboardService $alertes,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        $this->assertNotifications($request);
        $user = $request->user();

        $payload = [
            'stats' => $this->inbox->stats($user),
            'peut_gerer' => $this->peut($request, 'notifications.manage'),
            'peut_resoudre' => $this->peut($request, 'alertes.resolve'),
            'peut_exporter' => $this->peut($request, 'alertes.manage'),
            'peut_admin' => $this->peut($request, 'notifications.admin'),
            'href_export' => $this->peut($request, 'alertes.manage') ? $this->href('alertes.export') : null,
            'href_templates' => $this->peut($request, 'notifications.admin') ? $this->href('admin.notifications.templates.index') : null,
            'alertes' => null,
        ];

        if ($user?->can('alertes.view')) {
            $tableau = $this->alertes->tableau();
            $payload['alertes'] = [
                'kpi' => $tableau['kpi'],
            ];
        }

        return response()->json($payload);
    }

    public function index(Request $request): JsonResource
    {
        $this->assertNotifications($request);

        $items = $this->inbox->requete($request->user(), $request)
            ->paginate(min((int) $request->input('per_page', 20), 50))
            ->through(fn ($n) => $this->inbox->serialiser($n));

        return JsonResource::make($items);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->assertNotifications($request);

        return response()->json([
            'count' => $request->user()->unreadNotifications()->whereNull('archive_at')->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $this->assertNotifications($request);

        $resultat = $this->idempotence->executer($request, 'notification.lue.'.$id, function () use ($request, $id) {
            $this->inbox->marquerLue($request->user(), $id);

            return ['status' => 200, 'body' => ['ok' => true, 'id' => $id]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function toutLu(Request $request): JsonResponse
    {
        $this->assertNotifications($request);

        $resultat = $this->idempotence->executer($request, 'notification.tout-lu', function () use ($request) {
            $nb = $this->inbox->toutLu($request->user());

            return ['status' => 200, 'body' => ['ok' => true, 'marques' => $nb]];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function archiver(Request $request, string $id): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.manage'), 403);

        $resultat = $this->idempotence->executer($request, 'notification.archiver.'.$id, function () use ($request, $id) {
            $this->inbox->archiver($request->user(), $id);

            return ['status' => 200, 'body' => ['ok' => true, 'id' => $id, 'code' => 'archive']];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function preferences(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('notifications.manage'), 403);

        $prefs = $request->user()->preferences_notifications ?? [];

        return response()->json([
            'data' => [
                'actif' => (bool) ($prefs['actif'] ?? true),
                'database' => (bool) ($prefs['canaux']['database'] ?? true),
                'email' => (bool) ($prefs['canaux']['email'] ?? true),
                'sms' => (bool) ($prefs['canaux']['sms'] ?? false),
                'sms_critique_uniquement' => (bool) ($prefs['sms_critique_uniquement'] ?? true),
                'telephone' => $request->user()->telephone,
            ],
        ]);
    }

    public function enregistrerPreferences(
        Request $request,
        NotificationPreferenceService $preferences,
        AuditNotificationService $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('notifications.manage'), 403);

        $request->validate([
            'actif' => 'nullable|boolean',
            'database' => 'nullable|boolean',
            'email' => 'nullable|boolean',
            'sms' => 'nullable|boolean',
            'sms_critique_uniquement' => 'nullable|boolean',
        ]);

        $resultat = $this->idempotence->executer($request, 'notification.preferences', function () use ($request, $preferences, $audit) {
            $user = $preferences->enregistrer($request->user(), [
                'actif' => $request->boolean('actif', true),
                'database' => $request->boolean('database', true),
                'email' => $request->boolean('email', true),
                'sms' => $request->boolean('sms'),
                'sms_critique_uniquement' => $request->boolean('sms_critique_uniquement', true),
            ]);
            $audit->journaliser('preference_maj', utilisateur: $user, payload: $user->preferences_notifications ?? []);
            $prefs = $user->preferences_notifications ?? [];

            return [
                'status' => 200,
                'body' => [
                    'ok' => true,
                    'code' => 'modifie',
                    'data' => [
                        'actif' => (bool) ($prefs['actif'] ?? true),
                        'database' => (bool) ($prefs['canaux']['database'] ?? true),
                        'email' => (bool) ($prefs['canaux']['email'] ?? true),
                        'sms' => (bool) ($prefs['canaux']['sms'] ?? false),
                        'sms_critique_uniquement' => (bool) ($prefs['sms_critique_uniquement'] ?? true),
                    ],
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function templates(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'notifications.admin'), 403);

        $templates = EmailTemplate::query()
            ->with('notificationType')
            ->orderBy('code')
            ->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'data' => $templates->getCollection()->map(fn (EmailTemplate $t) => $this->formatTemplate($t, false))->values()->all(),
            'meta' => [
                'total' => $templates->total(),
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
            ],
        ]);
    }

    public function showTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        abort_unless($this->peut($request, 'notifications.admin'), 403);

        $template->loadMissing('notificationType');

        return response()->json(['data' => $this->formatTemplate($template, true)]);
    }

    public function updateTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        abort_unless($this->peut($request, 'notifications.admin'), 403);

        $data = $request->validate([
            'libelle' => 'required|string|max:255',
            'sujet' => 'required|string|max:255',
            'corps_html' => 'required|string',
            'corps_texte' => 'nullable|string',
            'actif' => 'nullable|boolean',
        ]);

        $template->update([
            ...$data,
            'actif' => $request->boolean('actif'),
        ]);

        return response()->json([
            'message' => 'Modèle e-mail mis à jour.',
            'data' => $this->formatTemplate($template->fresh('notificationType'), true),
        ]);
    }

    public function alertes(Request $request): JsonResource
    {
        abort_unless($request->user()->can('alertes.view'), 403);

        $query = Alerte::query()->with('structure')->latest('detecte_le');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }
        if ($request->filled('module')) {
            $query->where('module', $request->string('module'));
        }

        $items = $query->paginate(min((int) $request->input('per_page', 20), 50))->through(fn (Alerte $a) => [
            'id' => $a->id,
            'reference' => $a->reference,
            'module' => $a->module,
            'code' => $a->code,
            'criticite' => $a->criticite,
            'titre' => $a->titre,
            'message' => $a->message,
            'statut' => $a->statut,
            'escalade_niveau' => $a->escalade_niveau,
            'detecte_le' => $a->detecte_le?->toIso8601String(),
            'structure' => $a->structure ? [
                'code' => $a->structure->code,
                'libelle' => $a->structure->sigle ?: $a->structure->libelle,
            ] : null,
        ]);

        return JsonResource::make($items);
    }

    public function resolveAlerte(Request $request, Alerte $alerte, AlerteRegistryService $registry): JsonResponse
    {
        abort_unless($request->user()->can('alertes.resolve'), 403);

        if (! $alerte->estOuverte()) {
            return response()->json(['message' => 'Alerte déjà clôturée.', 'code' => 'transition_invalide'], 422);
        }

        $data = $request->validate(['commentaire' => 'nullable|string|max:1000']);

        $resultat = $this->idempotence->executer($request, 'alerte.resoudre.'.$alerte->id, function () use ($request, $alerte, $registry, $data) {
            $registry->resoudre($alerte, $request->user(), $data['commentaire'] ?? null);

            return ['status' => 200, 'body' => ['ok' => true, 'statut' => 'resolue']];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    private function assertNotifications(Request $request): void
    {
        abort_unless($this->peut($request, 'notifications.view'), 403);
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
    private function formatTemplate(EmailTemplate $template, bool $detail): array
    {
        $row = [
            'id' => $template->id,
            'code' => $template->code,
            'libelle' => $template->libelle,
            'sujet' => $template->sujet,
            'actif' => (bool) $template->actif,
            'type' => $template->notificationType?->libelle,
        ];

        if ($detail) {
            $row['corps_html'] = $template->corps_html;
            $row['corps_texte'] = $template->corps_texte;
        }

        return $row;
    }

    private function href(string $name, mixed ...$params): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, $params, false);
    }
}
