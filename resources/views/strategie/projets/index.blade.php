@extends('layouts.app')
@section('titre', 'Projets stratégiques')
@section('contenu')
@include('strategie._nav', ['active' => 'projets'])
<div class="d-flex justify-content-between mb-3">
    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'projets') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan
    @can('strategie.create')<a href="{{ route('strategie.projets.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouveau projet</a>@endcan
</div>
@include('strategie._filtres', ['statuts' => ['planifie','en_cours','suspendu','acheve','archive']])
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Programme</th><th>Responsable</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($projets as $pr)<tr>
<td><code>{{ $pr->code }}</code></td><td>{{ Str::limit($pr->intitule, 40) }}</td><td>{{ $pr->programmeStrategique?->code ?? '—' }}</td>
<td class="small">{{ $pr->responsable?->name ?? '—' }}</td><td>@include('strategie._statut', ['statut' => $pr->statut])</td>
<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.projets.show', $pr) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan
@can('strategie.update')<a href="{{ route('strategie.projets.edit', $pr) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
@include('strategie._actions', ['item' => $pr, 'entite' => 'projets', 'destroyRoute' => route('strategie.projets.destroy', $pr)])</td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun projet.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $projets->links() }}</div>
@endsection
