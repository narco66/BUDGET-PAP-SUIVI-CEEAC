@extends('layouts.app')
@section('titre', 'Programmations pluriannuelles')
@section('contenu')
@include('strategie._nav', ['active' => 'programmations-pluri'])
<div class="d-flex justify-content-end mb-3">@can('strategie.create')<a href="{{ route('strategie.programmations-pluri.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvelle programmation</a>@endcan</div>
@include('strategie._filtres')
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Réf.</th><th>Plan</th><th>Période</th><th>Annuelles</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($programmations as $pg)<tr>
<td><code>{{ $pg->reference }}</code></td><td>{{ $pg->planStrategique?->reference ?? '—' }}</td>
<td>{{ $pg->annee_debut }} — {{ $pg->annee_fin }}</td><td>{{ $pg->programmations_annuelles_count }}</td>
<td>@include('strategie._statut', ['statut' => $pg->statut])</td>
<td class="text-end"><a href="{{ route('strategie.programmations-pluri.show', $pg) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
@can('strategie.update')<a href="{{ route('strategie.programmations-pluri.edit', $pg) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan</td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucune programmation.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $programmations->links() }}</div>
@endsection
