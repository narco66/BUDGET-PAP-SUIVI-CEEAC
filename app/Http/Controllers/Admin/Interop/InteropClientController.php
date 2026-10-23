<?php

namespace App\Http\Controllers\Admin\Interop;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ConnectedApp;
use App\Services\Interop\ConnectedAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Gestion des clients API (clés) par application (I-P1). */
class InteropClientController extends Controller
{
    public function __construct(private readonly ConnectedAppService $apps) {}

    public function store(Request $request, ConnectedApp $app): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:100'],
        ]);

        $resultat = $this->apps->creerClient($app, $data['libelle']);

        return redirect()->route('admin.interoperabilite.apps.show', $app)
            ->with('succes', 'Client API créé — conservez le secret affiché, il ne sera plus visible.')
            ->with('nouveau_client_id', $resultat['client_id'])
            ->with('nouveau_client_secret', $resultat['client_secret']);
    }

    public function revoquer(Request $request, ConnectedApp $app, ApiClient $client): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);
        abort_unless($client->connected_app_id === $app->id, 404);

        $this->apps->revoquerClient($client, $request->user());

        return back()->with('succes', 'Client API révoqué.');
    }
}
