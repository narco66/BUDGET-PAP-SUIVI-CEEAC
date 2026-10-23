@extends('layouts.app')
@section('titre', 'Postes')

@section('contenu')
<div class="d-flex justify-content-end mb-3">
    @can('create', App\Models\Poste::class)
    <a href="{{ route('postes.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau poste</a>
    @endcan
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Fonction</th><th class="text-center">Utilisateurs</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($postes as $p)
                <tr>
                    <td><code>{{ $p->code }}</code></td>
                    <td>{{ $p->libelle }}</td>
                    <td class="small text-muted">{{ $p->fonction?->libelle ?? '—' }}</td>
                    <td class="text-center">{{ $p->utilisateurs_count }}</td>
                    <td class="text-center">@if($p->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td>
                    <td class="text-end">
                        @can('update', $p)
                        <a href="{{ route('postes.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @can('delete', $p)
                        <form action="{{ route('postes.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        @endcan
                    </td>
                </tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun poste.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $postes->links() }}</div>
@endsection
