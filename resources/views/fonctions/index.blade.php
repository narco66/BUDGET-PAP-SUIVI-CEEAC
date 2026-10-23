@extends('layouts.app')
@section('titre', 'Fonctions')

@section('contenu')
<div class="d-flex justify-content-end mb-3">
    @can('structure.creer')<a href="{{ route('fonctions.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle fonction</a>@endcan
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th class="text-center">Utilisateurs</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($fonctions as $f)
                <tr>
                    <td><code>{{ $f->code }}</code></td>
                    <td>{{ $f->libelle }}</td>
                    <td class="text-center">{{ $f->utilisateurs_count }}</td>
                    <td class="text-center">@if($f->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td>
                    <td class="text-end">
                        @can('structure.modifier')<a href="{{ route('fonctions.edit', $f) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                        @can('structure.supprimer')
                        <form action="{{ route('fonctions.destroy', $f) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        @endcan
                    </td>
                </tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-4">Aucune fonction.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $fonctions->links() }}</div>
@endsection
