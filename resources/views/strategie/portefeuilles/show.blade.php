@extends('layouts.app')
@section('titre', $portefeuille->reference)
@section('contenu')
@include('strategie._nav', ['active' => 'portefeuilles'])
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('strategie.portefeuilles.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    @can('strategie.update')<a href="{{ route('strategie.portefeuilles.edit', $portefeuille) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h5">{{ $portefeuille->intitule }}</h2>
    <p class="text-muted">{{ $portefeuille->description }}</p>
    <div class="small">Exercice : {{ $portefeuille->exercice?->annee ?? '—' }} · Responsable : {{ $portefeuille->responsable?->name ?? '—' }} · @include('strategie._statut', ['statut' => $portefeuille->statut])</div>
</div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white">Projets consolidés ({{ $portefeuille->projets->count() }})</div>
<div class="table-responsive"><table class="table mb-0"><thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Programme</th><th>Priorité</th><th>Statut</th></tr></thead>
<tbody>@forelse($portefeuille->projets as $pr)<tr>
<td><a href="{{ route('strategie.projets.show', $pr) }}"><code>{{ $pr->code }}</code></a></td>
<td>{{ $pr->intitule }}</td><td>{{ $pr->programmeStrategique?->code }}</td>
<td>{{ $pr->pivot->priorite ?? '—' }}</td><td>@include('strategie._statut', ['statut' => $pr->statut])</td>
</tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">Aucun projet rattaché.</td></tr>@endforelse</tbody></table></div></div>
@endsection
