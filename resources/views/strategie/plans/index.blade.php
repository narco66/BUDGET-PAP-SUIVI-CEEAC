@extends('layouts.app')
@section('titre', 'Plans stratégiques')
@section('contenu')
@include('strategie._nav', ['active' => 'plans'])
<div class="d-flex justify-content-between mb-3">
    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'plans') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan
    @can('strategie.create')<a href="{{ route('strategie.plans.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouveau plan</a>@endcan
</div>
@include('strategie._filtres')
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Réf.</th><th>Intitulé</th><th>Vision</th><th>Période</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($plans as $p)<tr>
<td><code>{{ $p->reference }}</code></td><td>{{ Str::limit($p->intitule, 50) }}</td><td>{{ $p->vision?->reference ?? '—' }}</td>
<td>{{ optional($p->date_debut)->format('Y') }}-{{ optional($p->date_fin)->format('Y') }}</td>
<td>@include('strategie._statut', ['statut' => $p->statut])</td>
<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.plans.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan
@can('strategie.update')<a href="{{ route('strategie.plans.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
@include('strategie._actions', ['item' => $p, 'entite' => 'plans', 'destroyRoute' => route('strategie.plans.destroy', $p)])</td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun plan.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $plans->links() }}</div>
@endsection
