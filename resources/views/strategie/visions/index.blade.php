@extends('layouts.app')
@section('titre', 'Visions institutionnelles')
@section('contenu')
@include('strategie._nav', ['active' => 'visions'])
<div class="d-flex justify-content-between mb-3">
    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'visions') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan
    @can('strategie.create')<a href="{{ route('strategie.visions.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouvelle vision</a>@endcan
</div>
@include('strategie._filtres', ['statuts' => ['actif','brouillon','archive','obsolete']])
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Réf.</th><th>Intitulé</th><th>Période</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($visions as $v)<tr>
<td><code>{{ $v->reference }}</code></td><td>{{ $v->intitule }}</td><td>{{ $v->periode ?? '—' }}</td>
<td>@include('strategie._statut', ['statut' => $v->statut])</td>
<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.visions.show', $v) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan
@can('strategie.update')<a href="{{ route('strategie.visions.edit', $v) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
@include('strategie._actions', ['item' => $v, 'entite' => 'visions', 'destroyRoute' => route('strategie.visions.destroy', $v)])</td>
</tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Aucune vision.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $visions->links() }}</div>
@endsection
