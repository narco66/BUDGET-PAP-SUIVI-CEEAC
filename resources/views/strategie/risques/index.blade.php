@extends('layouts.app')

@section('titre', 'Risques stratégiques')

@section('contenu')

@include('strategie._nav', ['active' => 'risques'])

<div class="d-flex justify-content-between mb-3">

    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'risques') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan

    @can('strategie.create')<a href="{{ route('strategie.risques.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouveau risque</a>@endcan

</div>

@include('strategie._filtres', ['statuts' => ['identifie','en_traitement','mitige','cloture']])

<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">

<thead class="table-light"><tr><th>Code</th><th>Description</th><th>Plan</th><th>Probab.</th><th>Impact</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>

<tbody>@forelse($risques as $r)<tr>

<td><code>{{ $r->code }}</code></td><td>{{ Str::limit($r->description, 50) }}</td>

<td>{{ $r->planStrategique?->reference ?? '—' }}</td><td>{{ ucfirst($r->probabilite) }}</td><td>{{ ucfirst($r->impact) }}</td>

<td>@include('strategie._statut', ['statut' => $r->statut])</td>

<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.risques.show', $r) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan

@can('strategie.update')<a href="{{ route('strategie.risques.edit', $r) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan

@include('strategie._actions', ['item' => $r, 'entite' => 'risques', 'destroyRoute' => route('strategie.risques.destroy', $r)])</td>

</tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">Aucun risque.</td></tr>@endforelse</tbody></table></div></div>

<div class="mt-3">{{ $risques->links() }}</div>

@endsection

