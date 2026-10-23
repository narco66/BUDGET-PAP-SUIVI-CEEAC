<?php



namespace App\Http\Controllers\Api\V1;



use App\Http\Controllers\Controller;

use App\Models\Alerte;

use App\Services\Alertes\AlerteRegistryService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;



/** API REST Notifications & Alertes (N-P3). */

class NotificationsApiController extends Controller

{

    public function index(Request $request): JsonResource

    {

        $this->assertNotifications($request);



        $query = $request->user()->notifications()->latest();



        if ($request->boolean('unread')) {

            $query->whereNull('read_at');

        }



        if ($request->filled('module')) {

            $query->where('module', $request->string('module'));

        }



        $items = $query->paginate(min((int) $request->input('per_page', 20), 50))->through(fn ($n) => [

            'id' => $n->id,

            'type_code' => $n->type_code ?? data_get($n->data, 'type_code'),

            'module' => $n->module ?? data_get($n->data, 'module'),

            'criticite' => $n->criticite ?? data_get($n->data, 'criticite'),

            'titre' => data_get($n->data, 'titre'),

            'message' => data_get($n->data, 'message'),

            'url' => data_get($n->data, 'url'),

            'lu' => $n->read_at !== null,

            'lu_le' => $n->read_at?->toIso8601String(),

            'cree_le' => $n->created_at?->toIso8601String(),

        ]);



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



        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();

        $notification->markAsRead();



        return response()->json(['ok' => true, 'id' => $id]);

    }



    public function alertes(Request $request): JsonResource

    {

        abort_unless($request->user()->can('alertes.view'), 403);



        $query = Alerte::query()->latest('detecte_le');



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

        ]);



        return JsonResource::make($items);

    }



    public function resolveAlerte(Request $request, Alerte $alerte, AlerteRegistryService $registry): JsonResponse

    {

        abort_unless($request->user()->can('alertes.resolve'), 403);



        $data = $request->validate(['commentaire' => 'nullable|string|max:1000']);

        $registry->resoudre($alerte, $request->user(), $data['commentaire'] ?? null);



        return response()->json(['ok' => true, 'statut' => 'resolue']);

    }



    private function assertNotifications(Request $request): void

    {

        abort_unless($request->user()->can('notifications.view'), 403);

    }

}

