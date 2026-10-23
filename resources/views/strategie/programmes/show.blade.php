@extends('layouts.app')
@section('titre', $programme->code)
@section('contenu')
@include('strategie._nav', ['active' => 'programmes'])
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('strategie.programmes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <div>@can('strategie.create')<a href="{{ route('strategie.projets.create', ['programme' => $programme->id]) }}" class="btn btn-sm btn-outline-primary me-1">+ Projet</a>@endcan
    @can('strategie.update')<a href="{{ route('strategie.programmes.edit', $programme) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan</div>
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h5">{{ $programme->intitule }}</h2>
    <p class="text-muted mb-1">{{ $programme->objectifs }}</p>
    <div class="small">Orientation : {{ $programme->orientationStrategique?->code }} · Responsable : {{ $programme->responsable?->name ?? '—' }} · Budget : {{ number_format($programme->budget_previsionnel, 0, ',', ' ') }} XAF</div>
</div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white">Projets ({{ $programme->projets->count() }})</div>
<ul class="list-group list-group-flush">@forelse($programme->projets as $pr)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('strategie.projets.show', $pr) }}">{{ $pr->code }} — {{ $pr->intitule }}</a>@include('strategie._statut', ['statut' => $pr->statut])</li>@empty<li class="list-group-item text-muted">Aucun projet.</li>@endforelse</ul></div>
@endsection
