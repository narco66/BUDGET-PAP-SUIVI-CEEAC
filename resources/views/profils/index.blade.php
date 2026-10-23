@extends('layouts.app')
@section('titre', 'Profils fonctionnels')
@section('contenu')
<div class="d-flex justify-content-end mb-3">@can('profil.creer')<a href="{{ route('profils.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau profil</a>@endcan</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Rôles</th><th class="text-center">Utilisateurs</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($profils as $p)
            <tr>
                <td><code>{{ $p->code }}</code></td>
                <td>{{ $p->libelle }}</td>
                <td><span class="badge bg-info-subtle text-info-emphasis">{{ str_replace('_',' ',$p->type) }}</span></td>
                <td>@foreach ($p->roles as $r)<span class="badge bg-primary-subtle text-primary-emphasis">{{ $r->libelle ?? $r->name }}</span> @endforeach</td>
                <td class="text-center">{{ $p->utilisateurs_count }}</td>
                <td class="text-end">
                    @can('profil.modifier')<a href="{{ route('profils.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                    @can('profil.supprimer')<form action="{{ route('profils.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endcan
                </td>
            </tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun profil.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $profils->links() }}</div>
@endsection
