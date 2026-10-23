@extends('layouts.app')
@section('titre', $label)

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="{{ route('rbm.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Hub RBM</a>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher…">
            <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Tous statuts</option>
                @foreach(['actif','inactif','archive'] as $s)
                    <option value="{{ $s }}" @selected(request('statut')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </form>
    </div>
    @can('indicateur.creer')
    <a href="{{ route('rbm.catalogues.create', $catalogue) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Ajouter</a>
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Ordre</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td><code>{{ $item->code }}</code></td>
                    <td>{{ $item->libelle }} @if($item->symbole ?? false)<span class="badge bg-light text-dark">{{ $item->symbole }}</span>@endif</td>
                    <td>{{ $item->ordre }}</td>
                    <td><span class="badge @if($item->statut==='actif') bg-success @elseif($item->statut==='archive') bg-secondary @else bg-warning text-dark @endif">{{ $item->statut }}</span></td>
                    <td class="text-end">
                        @can('indicateur.modifier')
                        <a href="{{ route('rbm.catalogues.edit', [$catalogue, $item->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Aucun élément.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif
</div>
@endsection
