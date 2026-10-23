<?php



namespace App\Http\Controllers;



use App\Models\NotificationType;

use App\Support\NotificationCriticite;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;



class NotificationController extends Controller

{

    public function index(Request $request): View

    {

        abort_unless($request->user()->can('notifications.view'), 403);



        $query = $request->user()->notifications()->latest();



        if ($request->filled('module')) {

            $module = $request->string('module')->toString();

            $query->where(function ($q) use ($module) {

                $q->where('module', $module)

                    ->orWhere('data->module', $module);

            });

        }



        if ($request->filled('type')) {

            $type = $request->string('type')->toString();

            $query->where(function ($q) use ($type) {

                $q->where('type_code', $type)

                    ->orWhere('data->type', $type)

                    ->orWhere('data->type_code', $type);

            });

        }



        if ($request->string('lu')->toString() === '1') {

            $query->whereNotNull('read_at');

        } elseif ($request->string('lu')->toString() === '0') {

            $query->whereNull('read_at');

        }



        if ($request->string('archive')->toString() === '1') {

            $query->whereNotNull('archive_at');

        } else {

            $query->whereNull('archive_at');

        }



        if ($request->filled('q')) {

            $terme = '%'.$request->string('q')->toString().'%';

            $query->where(function ($q) use ($terme) {

                $q->where('data->titre', 'like', $terme)

                    ->orWhere('data->message', 'like', $terme);

            });

        }



        $notifications = $query->paginate(20)->withQueryString();



        $stats = [

            'non_lues' => $request->user()->unreadNotifications()->whereNull('archive_at')->count(),

            'aujourdhui' => $request->user()->notifications()->whereNull('archive_at')->whereDate('created_at', today())->count(),

            'critiques' => $request->user()->notifications()

                ->whereNull('archive_at')

                ->whereIn('criticite', [NotificationCriticite::CRITIQUE, NotificationCriticite::URGENCE])

                ->count(),

        ];



        return view('notifications.index', [

            'notifications' => $notifications,

            'stats' => $stats,

            'modules' => NotificationType::query()->where('actif', true)->distinct()->orderBy('module')->pluck('module'),

            'types' => NotificationType::query()->where('actif', true)->orderBy('module')->orderBy('code')->get(['code', 'libelle', 'module']),

            'filtres' => $request->only(['module', 'type', 'lu', 'archive', 'q']),

        ]);

    }



    public function marquerLue(Request $request, string $id): RedirectResponse

    {

        abort_unless($request->user()->can('notifications.view'), 403);



        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();

        $notification->markAsRead();



        return back()->with('succes', 'Notification marquée comme lue.');

    }



    public function toutMarquerLu(Request $request): RedirectResponse

    {

        abort_unless($request->user()->can('notifications.view'), 403);



        $request->user()->unreadNotifications()->whereNull('archive_at')->update(['read_at' => now()]);



        return back()->with('succes', 'Toutes les notifications ont été marquées comme lues.');

    }



    public function archiver(Request $request, string $id): RedirectResponse

    {

        abort_unless($request->user()->can('notifications.manage'), 403);



        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();

        $notification->forceFill(['archive_at' => now()])->save();



        return back()->with('succes', 'Notification archivée.');

    }

}

