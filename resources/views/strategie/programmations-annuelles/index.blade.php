@extends('layouts.app')
@section('titre', 'Programmations annuelles')
@section('contenu')
@include('strategie._nav', ['active' => 'programmations-annuelles'])
<div class="d-flex justify-content-end mb-3">@can('strategie.create')<a href="{{ route('strategie.programmations-annuelles.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvelle</a>@endcan</div>
@include('strategie._filtres')
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Exercice</th><th>Programmation pluri.</th><th>Plan</th><th>PAP</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($programmations as $ann)<tr>
<td><strong>{{ $ann->exercice?->annee }}</strong></td>
<td>{{ $ann->programmationPluriannuelle?->reference ?? '—' }}</td>
<td>{{ $ann->programmationPluriannuelle?->planStrategique?->reference ?? '—' }}</td>
<td>@if($ann->pap)<span class="badge bg-success">Lié</span>@else<span class="badge bg-warning text-dark">—</span>@endif</td>
<td>@include('strategie._statut', ['statut' => $ann->statut])</td>
<td class="text-end"><a href="{{ route('strategie.programmations-annuelles.show', $ann) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucune programmation annuelle.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $programmations->links() }}</div>
@endsection
