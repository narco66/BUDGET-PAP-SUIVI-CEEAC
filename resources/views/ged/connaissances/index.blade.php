@extends('layouts.app')
@section('titre', 'Base de connaissances')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Wiki & procédures</h4>
        <span class="text-muted small">Gestion des connaissances institutionnelles · GED CEEAC</span>
    </div>
    @can('ged.creer')
    <div class="d-flex gap-2">
        <a href="{{ route('ged.connaissances.create', ['type' => 'wiki']) }}" class="btn btn-primary btn-sm"><i class="bi bi-journal-plus me-1"></i>Nouvel article</a>
        <a href="{{ route('ged.connaissances.create', ['type' => 'procedure']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-check me-1"></i>Nouvelle procédure</a>
    </div>
    @endcan
</div>

<div class="row g-3 mb-3">
    <div class="col-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ $synthese['total'] }}</div><div class="text-muted small">Contenus visibles</div></div></div></div>
    <div class="col-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="fs-5 fw-bold text-primary">{{ $synthese['wiki'] }}</div><div class="text-muted small">Articles wiki publiés</div></div></div></div>
    <div class="col-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="fs-5 fw-bold text-success">{{ $synthese['procedures'] }}</div><div class="text-muted small">Procédures publiées</div></div></div></div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher…"></div>
    <div class="col-auto">
        <select name="type" class="form-select form-select-sm"><option value="">Tous types</option>
            @foreach ($types as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="module" class="form-select form-select-sm"><option value="">Tous modules</option>
            @foreach ($modules as $k => $v)<option value="{{ $k }}" @selected(request('module') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    @can('ged.admin')
    <div class="col-auto">
        <select name="statut" class="form-select form-select-sm"><option value="">Tous statuts</option>
            @foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    @endcan
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Réf.</th><th>Titre</th><th>Type</th><th>Module</th><th>Statut</th><th>Version</th><th>Auteur</th><th class="text-end"></th></tr></thead>
        <tbody>
        @forelse ($connaissances as $c)
            <tr>
                <td class="font-monospace small">{{ $c->reference }}</td>
                <td>{{ \Illuminate\Support\Str::limit($c->titre, 42) }}</td>
                <td class="small"><span class="badge bg-{{ $c->type === 'procedure' ? 'success' : 'info' }}">{{ $types[$c->type] ?? $c->type }}</span></td>
                <td class="small">{{ $modules[$c->module] ?? $c->module }}</td>
                <td class="small"><span class="badge bg-{{ $c->statut === 'publie' ? 'success' : ($c->statut === 'archive' ? 'secondary' : 'warning') }}">{{ $statuts[$c->statut] ?? $c->statut }}</span></td>
                <td class="small text-muted">v{{ $c->version }}</td>
                <td class="small">{{ $c->auteur?->name ?? '—' }}</td>
                <td class="text-end"><a href="{{ route('ged.connaissances.show', $c) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Aucun contenu. @can('ged.creer')<a href="{{ route('ged.connaissances.create') }}">Créer le premier article</a>@endcan</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $connaissances->links() }}</div>
@endsection
