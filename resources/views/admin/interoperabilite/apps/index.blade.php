@extends('layouts.app')
@section('titre', 'Applications connectées')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-plug me-2"></i>Applications connectées</h4>
    <a href="{{ route('admin.interoperabilite.apps.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle application</a>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'apps'])

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Libellé</th><th>Type</th><th>Scopes</th><th>Clients</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($apps as $app)
                <tr>
                    <td><code>{{ $app->code }}</code></td>
                    <td>{{ $app->libelle }}</td>
                    <td class="small">{{ config('interoperabilite.app_types.'.$app->type, $app->type) }}</td>
                    <td class="small">@foreach($app->scopes_autorises as $s)<span class="badge bg-light text-dark border me-1">{{ $s }}</span>@endforeach</td>
                    <td>{{ $app->clients_count }}</td>
                    <td>
                        @if($app->statut === 'actif')<span class="badge bg-success">Actif</span>
                        @elseif($app->statut === 'suspendu')<span class="badge bg-warning text-dark">Suspendu</span>
                        @else<span class="badge bg-secondary">Révoqué</span>@endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.interoperabilite.apps.show', $app) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.interoperabilite.apps.edit', $app) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Aucune application — créez la première ou exécutez InteroperabiliteApiSeeder.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($apps->hasPages())<div class="card-footer">{{ $apps->links() }}</div>@endif
</div>
@endsection
