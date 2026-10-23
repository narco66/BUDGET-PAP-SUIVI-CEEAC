@extends('layouts.app')
@section('titre', 'Journal des échanges API')

@section('contenu')
<div class="mb-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Journal des échanges API</h4>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'journal'])

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3"><input name="chemin" value="{{ request('chemin') }}" class="form-control form-control-sm" placeholder="Chemin…"></div>
    <div class="col-md-2">
        <select name="statut_metier" class="form-select form-select-sm">
            <option value="">Statut métier</option>
            @foreach(['succes','erreur','rejet'] as $s)
                <option value="{{ $s }}" @selected(request('statut_metier')===$s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2"><input type="number" name="statut" value="{{ request('statut') }}" class="form-control form-control-sm" placeholder="HTTP"></div>
    <div class="col-md-2">
        <select name="connected_app_id" class="form-select form-select-sm">
            <option value="">Application</option>
            @foreach($apps as $app)
                <option value="{{ $app->id }}" @selected((string) request('connected_app_id') === (string) $app->id)>{{ $app->code }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2"><input type="date" name="depuis" value="{{ request('depuis') }}" class="form-control form-control-sm"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Méthode</th><th>Chemin</th><th>HTTP</th><th>Métier</th><th>App</th><th>Utilisateur</th><th>ms</th><th>IP</th></tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="small">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $log->methode }}</span></td>
                    <td class="small"><code>{{ Str::limit($log->chemin, 50) }}</code></td>
                    <td>{{ $log->statut }}</td>
                    <td>
                        @if($log->statut_metier === 'succes')<span class="badge bg-success">succès</span>
                        @elseif($log->statut_metier === 'rejet')<span class="badge bg-warning text-dark">rejet</span>
                        @else<span class="badge bg-danger">erreur</span>@endif
                    </td>
                    <td class="small">{{ $apps->firstWhere('id', $log->connected_app_id)?->code ?? '—' }}</td>
                    <td class="small">{{ $log->user?->name ?? '—' }}</td>
                    <td class="small">{{ $log->duree_ms }}</td>
                    <td class="small text-muted">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Aucun échange enregistré.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
