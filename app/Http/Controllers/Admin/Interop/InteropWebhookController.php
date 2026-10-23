<?php

namespace App\Http\Controllers\Admin\Interop;

use App\Http\Controllers\Controller;
use App\Models\ConnectedApp;
use App\Models\Webhook;
use App\Models\WebhookEvent;
use App\Models\WebhookLivraison;
use App\Services\WebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Gestion MOA des abonnements webhooks (I-P2). */
class InteropWebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $webhooks = Webhook::with('connectedApp')
            ->when($request->filled('evenement'), fn ($q) => $q->where('evenement', $request->evenement))
            ->orderBy('libelle')
            ->paginate(20)
            ->withQueryString();

        return view('admin.interoperabilite.webhooks.index', [
            'webhooks' => $webhooks,
            'evenements' => WebhookEvent::where('actif', true)->orderBy('module')->orderBy('code')->get(),
            'page' => 'webhooks',
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        return view('admin.interoperabilite.webhooks.form', [
            'webhook' => new Webhook(['actif' => true, 'methode' => 'POST']),
            'evenements' => WebhookEvent::where('actif', true)->orderBy('module')->orderBy('code')->get(),
            'apps' => ConnectedApp::where('statut', 'actif')->orderBy('code')->get(),
            'page' => 'webhooks',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $data = $this->valider($request);
        $data['secret'] = ($data['secret'] ?? '') ?: Str::random(32);
        $data['actif'] = $request->boolean('actif', true);

        Webhook::create($data);

        return redirect()->route('admin.interoperabilite.webhooks.index')
            ->with('succes', 'Webhook enregistré.');
    }

    public function show(Request $request, Webhook $webhook): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $livraisons = WebhookLivraison::where('webhook_id', $webhook->id)
            ->latest('created_at')
            ->paginate(30);

        return view('admin.interoperabilite.webhooks.show', [
            'webhook' => $webhook->load('connectedApp'),
            'livraisons' => $livraisons,
            'page' => 'webhooks',
        ]);
    }

    public function edit(Request $request, Webhook $webhook): View
    {
        abort_unless($request->user()->can('api.admin'), 403);

        return view('admin.interoperabilite.webhooks.form', [
            'webhook' => $webhook,
            'evenements' => WebhookEvent::where('actif', true)->orderBy('module')->orderBy('code')->get(),
            'apps' => ConnectedApp::where('statut', 'actif')->orderBy('code')->get(),
            'page' => 'webhooks',
        ]);
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $data = $this->valider($request, $webhook);
        $data['actif'] = $request->boolean('actif');
        if (empty($data['secret'])) {
            unset($data['secret']);
        }

        $webhook->update($data);

        return redirect()->route('admin.interoperabilite.webhooks.show', $webhook)
            ->with('succes', 'Webhook mis à jour.');
    }

    public function destroy(Request $request, Webhook $webhook): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $webhook->delete();

        return redirect()->route('admin.interoperabilite.webhooks.index')
            ->with('succes', 'Webhook supprimé.');
    }

    public function tester(Request $request, Webhook $webhook): RedirectResponse
    {
        abort_unless($request->user()->can('api.admin'), 403);

        $livraison = $this->webhooks->tester($webhook);

        $message = $livraison->succes
            ? 'Test réussi — livraison HTTP OK.'
            : 'Test envoyé — échec HTTP (voir le journal de livraisons).';

        return back()->with('succes', $message);
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?Webhook $webhook = null): array
    {
        $codes = WebhookEvent::where('actif', true)->pluck('code')->implode(',');

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
}
