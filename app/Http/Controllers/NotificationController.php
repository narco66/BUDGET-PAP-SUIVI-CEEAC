<?php

namespace App\Http\Controllers;

use App\Services\Notifications\AuditNotificationService;
use App\Services\Notifications\NotificationActionResolver;
use App\Services\Notifications\NotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
        private readonly NotificationActionResolver $resolver,
        private readonly AuditNotificationService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        $filtres = $this->inbox->filtres();

        return view('notifications.index', [
            'notifications' => $this->inbox->requete($request->user(), $request)->paginate(20)->withQueryString(),
            'stats' => $this->inbox->stats($request->user()),
            'modules' => $filtres['modules'],
            'types' => $filtres['types'],
            'natures' => $filtres['natures'],
            'priorites' => $filtres['priorites'],
            'actions' => $filtres['actions'],
            'filtres' => $request->only(NotificationInboxService::FILTRES),
        ]);
    }

    /** Panneau rapide de la cloche : compteur et dernières notifications (§ 8.2). */
    public function apercu(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);
        $user = $request->user();

        return response()->json([
            'non_lues' => $this->inbox->nonLues($user),
            'actions_en_attente' => $user->notifications()->whereNull('archive_at')->where('action_statut', 'en_attente')->count(),
            'items' => $user->notifications()->whereNull('archive_at')->latest()->limit(8)->get()
                ->map(fn ($n) => $this->inbox->serialiser($n))->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function marquerLue(Request $request, string $id): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);
        $this->inbox->marquerLue($request->user(), $id);

        return $request->expectsJson()
            ? response()->json(['non_lues' => $this->inbox->nonLues($request->user())])
            : back()->with('succes', 'Notification marquée comme lue.');
    }

    public function marquerNonLue(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);
        $this->inbox->marquerNonLue($request->user(), $id);

        return back()->with('succes', 'Notification marquée comme non lue.');
    }

    public function toutMarquerLu(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);
        $nombre = $this->inbox->toutLu($request->user());

        return $request->expectsJson()
            ? response()->json(['non_lues' => 0, 'marquees' => $nombre])
            : back()->with('succes', 'Toutes les notifications ont été marquées comme lues.');
    }

    public function archiver(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.manage'), 403);
        $this->inbox->archiver($request->user(), $id);

        return back()->with('succes', 'Notification archivée.');
    }

    /**
     * « Voir » (§ 9) : notification du seul destinataire, état d'action recalculé, consultation tracée,
     * lecture enregistrée, puis navigation vers un lien interne. La page cible applique ses propres
     * contrôles d'habilitation ; une action obsolète est signalée et n'est plus proposée.
     */
    public function ouvrir(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);
        $notification = $this->inbox->trouver($request->user(), $id);
        $destination = $this->resolver->destination($notification, $request->user());
        $notification->markAsRead();
        $this->audit->journaliser('consultation', $notification->type_code, $notification->module, 'database', $request->user(), $request->user(),
            payload: ['notification_id' => $notification->id, 'action_disponible' => $destination['action_disponible']]);

        $redirection = redirect($destination['url']);

        return $destination['message'] ? $redirection->with('info', $destination['message']) : $redirection;
    }
}
