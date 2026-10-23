@extends('layouts.app')
@section('titre', 'Programmes')

@section('contenu')
<div class="d-flex justify-content-end mb-3">
    @can('structure.creer')<a href="{{ route('programmes.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau programme</a>@endcan
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Structure</th><th>Pilier</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($programmes as $p)
                <tr>
                    <td><code>{{ $p->code }}</code></td>
                    <td>{{ $p->intitule }}</td>
                    <td class="small text-muted">{{ $p->structure?->libelle ?? '—' }}</td>
                    <td class="small text-muted">{{ $p->pilier?->intitule ?? '—' }}</td>
                    <td class="text-center">@if($p->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td>
                    <td class="text-end">
                        @can('structure.modifier')<a href="{{ route('programmes.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                        @can('structure.supprimer')<form action="{{ route('programmes.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endcan
                    </td>
                </tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun programme.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $programmes->links() }}</div>
@endsection
