<?php

namespace App\Http\Controllers\Admin\Interop;

use App\Http\Controllers\Controller;
use App\Models\ConnectedApp;
use App\Services\Interop\ConnectedAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** CRUD registre applications connectées (I-P1). */
class InteropAppController extends Controller
{
    public function __construct(private readonly ConnectedAppService $apps) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $apps = ConnectedApp::withCount(['clients' => fn ($q) => $q->where('statut', 'actif')])
            ->orderBy('code')
            ->paginate(20);

        return view('admin.interoperabilite.apps.index', [
            'apps' => $apps,
            'page' => 'apps',
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        return view('admin.interoperabilite.apps.form', [
            'app' => new ConnectedApp(['statut' => 'actif', 'type' => 'interne', 'scopes_autorises' => ['lecture']]),
            'page' => 'apps',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $data = $this->valider($request);
        $data['created_by'] = $request->user()->id;
        $data['scopes_autorises'] = array_values($data['scopes_autorises']);

        ConnectedApp::create($data);

        return redirect()->route('admin.interoperabilite.apps.index')
            ->with('succes', 'Application enregistrée.');
    }

    public function show(Request $request, ConnectedApp $app): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $app->load(['clients' => fn ($q) => $q->latest()]);

        return view('admin.interoperabilite.apps.show', [
            'app' => $app,
            'scopesDisponibles' => config('interoperabilite.token_abilities', []),
            'page' => 'apps',
        ]);
    }

    public function edit(Request $request, ConnectedApp $app): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        return view('admin.interoperabilite.apps.form', [
            'app' => $app,
            'page' => 'apps',
        ]);
    }

    public function update(Request $request, ConnectedApp $app): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $data = $this->valider($request, $app);
        $data['scopes_autorises'] = array_values($data['scopes_autorises']);

        $app->update($data);

        return redirect()->route('admin.interoperabilite.apps.show', $app)
            ->with('succes', 'Application mise à jour.');
    }

    public function suspendre(Request $request, ConnectedApp $app): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $this->apps->suspendreApplication($app, $request->user());

        return back()->with('succes', 'Application suspendue et jetons révoqués.');
    }

    public function reactiver(Request $request, ConnectedApp $app): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $this->apps->reactiverApplication($app);

        return back()->with('succes', 'Application réactivée.');
    }

    public function revoquerJetons(Request $request, ConnectedApp $app): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $nb = $this->apps->revoquerJetonsApplication($app);

        return back()->with('succes', "{$nb} jeton(s) révoqué(s).");
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?ConnectedApp $app = null): array
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
}
