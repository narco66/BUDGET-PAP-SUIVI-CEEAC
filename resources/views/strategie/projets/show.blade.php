@extends('layouts.app')
@section('titre', $projet->code)
@section('contenu')
@include('strategie._nav', ['active' => 'projets'])
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('strategie.projets.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    @can('strategie.update')<a href="{{ route('strategie.projets.edit', $projet) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h5">{{ $projet->intitule }}</h2>
    <p class="text-muted">{{ $projet->description }}</p>
    <div class="row small g-2">
        <div class="col-md-4">Programme : <a href="{{ route('strategie.programmes.show', $projet->programmeStrategique) }}">{{ $projet->programmeStrategique?->code }}</a></div>
        <div class="col-md-4">Responsable : {{ $projet->responsable?->name }}</div>
        <div class="col-md-4">@include('strategie._statut', ['statut' => $projet->statut]) · {{ ucfirst($projet->cycle_vie) }}</div>
        <div class="col-md-4">Budget prév. : {{ number_format($projet->budget_previsionnel, 0, ',', ' ') }} XAF</div>
        <div class="col-md-4">Engagé : {{ number_format($projet->budget_engage, 0, ',', ' ') }} XAF</div>
        <div class="col-md-4">Pilier RBM : {{ $projet->pilier?->code ?? '—' }}</div>
    </div>
</div></div>
@if($projet->portefeuilles->isNotEmpty())
<div class="card border-0 shadow-sm"><div class="card-header bg-white">Portefeuilles</div>
<ul class="list-group list-group-flush">@foreach($projet->portefeuilles as $pf)<li class="list-group-item"><a href="{{ route('strategie.portefeuilles.show', $pf) }}">{{ $pf->reference }} — {{ $pf->intitule }}</a></li>@endforeach</ul></div>
@endif
@endsection
