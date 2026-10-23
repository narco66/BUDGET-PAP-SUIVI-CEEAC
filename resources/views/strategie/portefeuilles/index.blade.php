@extends('layouts.app')
@section('titre', 'Portefeuilles de projets')
@section('contenu')
@include('strategie._nav', ['active' => 'portefeuilles'])
<div class="d-flex justify-content-between mb-3">
    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'portefeuilles') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan
    @can('strategie.create')<a href="{{ route('strategie.portefeuilles.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouveau portefeuille</a>@endcan
</div>
@include('strategie._filtres', ['statuts' => ['actif','archive']])
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Réf.</th><th>Intitulé</th><th>Exercice</th><th>Projets</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($portefeuilles as $pf)<tr>
<td><code>{{ $pf->reference }}</code></td><td>{{ Str::limit($pf->intitule, 45) }}</td><td>{{ $pf->exercice?->annee ?? '—' }}</td>
<td class="text-center">{{ $pf->projets_count }}</td><td>@include('strategie._statut', ['statut' => $pf->statut])</td>
<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.portefeuilles.show', $pf) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan
@can('strategie.update')<a href="{{ route('strategie.portefeuilles.edit', $pf) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
@include('strategie._actions', ['item' => $pf, 'entite' => 'portefeuilles', 'destroyRoute' => route('strategie.portefeuilles.destroy', $pf)])</td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun portefeuille.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $portefeuilles->links() }}</div>
@endsection
