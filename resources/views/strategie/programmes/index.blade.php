@extends('layouts.app')
@section('titre', 'Programmes stratégiques')
@section('contenu')
@include('strategie._nav', ['active' => 'programmes'])
<div class="d-flex justify-content-between mb-3">
    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'programmes') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan
    @can('strategie.create')<a href="{{ route('strategie.programmes.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouveau programme</a>@endcan
</div>
@include('strategie._filtres')
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Orientation</th><th>Budget prév.</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($programmes as $pg)<tr>
<td><code>{{ $pg->code }}</code></td><td>{{ Str::limit($pg->intitule, 45) }}</td><td>{{ $pg->orientationStrategique?->code ?? '—' }}</td>
<td class="text-end">{{ number_format($pg->budget_previsionnel, 0, ',', ' ') }}</td>
<td>@include('strategie._statut', ['statut' => $pg->statut])</td>
<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.programmes.show', $pg) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan
@can('strategie.update')<a href="{{ route('strategie.programmes.edit', $pg) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
@include('strategie._actions', ['item' => $pg, 'entite' => 'programmes', 'destroyRoute' => route('strategie.programmes.destroy', $pg)])</td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun programme.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $programmes->links() }}</div>
@endsection
