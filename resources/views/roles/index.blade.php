@extends('layouts.app')
@section('titre', 'Rôles')

@section('contenu')
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('permissions.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-grid-3x3 me-1"></i>Matrice rôles/permissions</a>
    @can('role.creer')<a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau rôle</a>@endcan
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Type</th><th class="text-center">Permissions</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @foreach ($roles as $role)
            <tr>
                <td><code>{{ $role->name }}</code> @if($role->protege)<i class="bi bi-shield-lock text-warning" title="Protégé"></i>@endif</td>
                <td>{{ $role->libelle ?? '—' }}</td>
                <td><span class="badge bg-{{ $role->type==='systeme'?'dark':'info' }}-subtle text-{{ $role->type==='systeme'?'dark':'info' }}-emphasis">{{ $role->type }}</span></td>
                <td class="text-center">{{ $role->permissions_count }}</td>
                <td class="text-end">
                    @can('update', $role)<a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                    @can('delete', $role)
                    <form action="{{ route('roles.destroy', $role) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce rôle ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    @endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>
@endsection
