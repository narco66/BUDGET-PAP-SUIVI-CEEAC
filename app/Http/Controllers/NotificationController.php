<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationInboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationInboxService $inbox) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        $filtres = $this->inbox->filtres();

        return view('notifications.index', [
            'notifications' => $this->inbox->requete($request->user(), $request)->paginate(20)->withQueryString(),
            'stats' => $this->inbox->stats($request->user()),
            'modules' => $filtres['modules'],
            'types' => $filtres['types'],
            'filtres' => $request->only(['module', 'type', 'lu', 'archive', 'q']),
        ]);
    }

    public function marquerLue(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);
        $this->inbox->marquerLue($request->user(), $id);

        return back()->with('succes', 'Notification marquée comme lue.');
    }

    public function toutMarquerLu(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);
        $this->inbox->toutLu($request->user());

        return back()->with('succes', 'Toutes les notifications ont été marquées comme lues.');
    }

    public function archiver(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.manage'), 403);
        $this->inbox->archiver($request->user(), $id);

        return back()->with('succes', 'Notification archivée.');
    }
}
