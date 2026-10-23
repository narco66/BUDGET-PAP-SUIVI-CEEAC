@extends('layouts.app')
@section('titre', $vision->reference)
@section('contenu')
@include('strategie._nav', ['active' => 'visions'])
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('strategie.visions.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    @can('strategie.update')<a href="{{ route('strategie.visions.edit', $vision) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h5">{{ $vision->intitule }}</h2>
    <p class="text-muted mb-2">{{ $vision->description }}</p>
    <div class="small">Période : {{ $vision->periode ?? '—' }} · @include('strategie._statut', ['statut' => $vision->statut]) · v{{ $vision->version }}</div>
</div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white">Plans rattachés ({{ $vision->plansStrategiques->count() }})</div>
<ul class="list-group list-group-flush">@forelse($vision->plansStrategiques as $p)<li class="list-group-item"><a href="{{ route('strategie.plans.show', $p) }}">{{ $p->reference }} — {{ $p->intitule }}</a></li>@empty<li class="list-group-item text-muted">Aucun plan.</li>@endforelse</ul></div>
<div class="mt-3">@include('strategie._documents', ['attachable' => $vision, 'type' => 'vision'])</div>
@endsection
