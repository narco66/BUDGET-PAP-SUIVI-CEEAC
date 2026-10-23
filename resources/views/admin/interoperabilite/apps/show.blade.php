@extends('layouts.app')
@section('titre', $app->libelle)

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-2">
    <div>
        <h4 class="fw-bold mb-0"><code>{{ $app->code }}</code> — {{ $app->libelle }}</h4>
        <span class="text-muted small">{{ $app->description }}</span>
    </div>
    <a href="{{ route('admin.interoperabilite.apps.edit', $app) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Modifier</a>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'apps'])

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif
@if (session('nouveau_client_secret'))
<div class="alert alert-warning">
    <strong>Identifiants client — à conserver immédiatement</strong><br>
    <span class="small">Client ID : <code>{{ session('nouveau_client_id') }}</code></span><br>
    <span class="small">Client secret : <code>{{ session('nouveau_client_secret') }}</code></span>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ config('interoperabilite.app_types.'.$app->type, $app->type) }}</dd>
                <dt class="col-sm-3">Contact</dt><dd class="col-sm-9">{{ $app->contact_email ?? '—' }}</dd>
                <dt class="col-sm-3">Statut</dt><dd class="col-sm-9">{{ ucfirst($app->statut) }}</dd>
                <dt class="col-sm-3">Scopes</dt>
                <dd class="col-sm-9">@foreach($app->scopes_autorises as $s)<span class="badge bg-light text-dark border me-1">{{ $s }}</span>@endforeach</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body d-grid gap-2">
            @if($app->estActive())
                <form method="POST" action="{{ route('admin.interoperabilite.apps.suspendre', $app) }}" onsubmit="return confirm('Suspendre cette application et révoquer ses clients ?');">
                    @csrf
                    <button class="btn btn-warning btn-sm w-100">Suspendre</button>
                </form>
            @elseif($app->estSuspendue())
                <form method="POST" action="{{ route('admin.interoperabilite.apps.reactiver', $app) }}">@csrf<button class="btn btn-success btn-sm w-100">Réactiver</button></form>
            @endif
            <form method="POST" action="{{ route('admin.interoperabilite.apps.revoquer-jetons', $app) }}" onsubmit="return confirm('Révoquer tous les jetons de cette application ?');">
                @csrf
                <button class="btn btn-outline-danger btn-sm w-100">Révoquer tous les jetons</button>
            </form>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Clients API (clés d'accès)</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Libellé</th><th>Client ID</th><th>Statut</th><th>Dernière utilisation</th><th></th></tr></thead>
            <tbody>
            @forelse($app->clients as $client)
                <tr>
                    <td>{{ $client->libelle }}</td>
                    <td><code class="small">{{ $client->client_id }}</code></td>
                    <td>@if($client->estActif())<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Révoqué</span>@endif</td>
                    <td class="small text-muted">{{ $client->derniere_utilisation_le?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-end">
                        @if($client->estActif())
                        <form method="POST" action="{{ route('admin.interoperabilite.apps.clients.revoquer', [$app, $client]) }}" class="d-inline" onsubmit="return confirm('Révoquer ce client ?');">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Révoquer</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">Aucun client — créez une clé ci-dessous.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($app->estActive())
    <div class="card-footer">
        <form method="POST" action="{{ route('admin.interoperabilite.apps.clients.store', $app) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label small mb-0">Libellé du client</label>
                <input name="libelle" class="form-control form-control-sm" value="Production" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary btn-sm"><i class="bi bi-key me-1"></i>Générer une clé</button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection
