@extends('layouts.app')
@section('titre', 'Orientations stratégiques')
@section('contenu')
@include('strategie._nav', ['active' => 'orientations'])
<div class="d-flex justify-content-between mb-3">
    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'orientations') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan
    @can('strategie.create')<a href="{{ route('strategie.orientations.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouvelle orientation</a>@endcan
</div>
@include('strategie._filtres', ['statuts' => ['actif','inactif','archive']])
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Plan</th><th>Ordre</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($orientations as $o)<tr>
<td><code>{{ $o->code }}</code></td><td>{{ Str::limit($o->intitule, 50) }}</td><td>{{ $o->planStrategique?->reference ?? '—' }}</td><td>{{ $o->ordre }}</td>
<td>@include('strategie._statut', ['statut' => $o->statut])</td>
<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.orientations.show', $o) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan
@can('strategie.update')<a href="{{ route('strategie.orientations.edit', $o) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
@include('strategie._actions', ['item' => $o, 'entite' => 'orientations', 'destroyRoute' => route('strategie.orientations.destroy', $o)])</td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucune orientation.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $orientations->links() }}</div>
@endsection
