@extends('layouts.app')
@section('titre', $orientation->code)
@section('contenu')
@include('strategie._nav', ['active' => 'orientations'])
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('strategie.orientations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <div>@can('strategie.create')<a href="{{ route('strategie.programmes.create', ['orientation' => $orientation->id]) }}" class="btn btn-sm btn-outline-primary me-1">+ Programme</a>@endcan
    @can('strategie.update')<a href="{{ route('strategie.orientations.edit', $orientation) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan</div>
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h5">{{ $orientation->intitule }}</h2>
    <p class="text-muted">{{ $orientation->description }}</p>
    <div class="small">Plan : <a href="{{ route('strategie.plans.show', $orientation->planStrategique) }}">{{ $orientation->planStrategique?->reference }}</a> · @include('strategie._statut', ['statut' => $orientation->statut])</div>
</div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white">Programmes stratégiques ({{ $orientation->programmesStrategiques->count() }})</div>
<ul class="list-group list-group-flush">@forelse($orientation->programmesStrategiques as $pg)<li class="list-group-item"><a href="{{ route('strategie.programmes.show', $pg) }}">{{ $pg->code }} — {{ $pg->intitule }}</a></li>@empty<li class="list-group-item text-muted">Aucun programme.</li>@endforelse</ul></div>
@endsection
