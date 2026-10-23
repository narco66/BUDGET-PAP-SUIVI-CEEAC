@extends('layouts.app')

@section('titre', $objectif->code)

@section('contenu')

@include('strategie._nav', ['active' => 'objectifs'])

<div class="d-flex justify-content-between mb-3">

    <a href="{{ route('strategie.objectifs.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>

    <div>@can('strategie.create')<a href="{{ route('strategie.indicateurs.create', ['objectif' => $objectif->id]) }}" class="btn btn-sm btn-outline-primary me-1">+ Indicateur</a>@endcan

    @can('strategie.update')<a href="{{ route('strategie.objectifs.edit', $objectif) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan</div>

</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">

    <h2 class="h5">{{ $objectif->intitule }}</h2>

    <p class="text-muted">{{ $objectif->description }}</p>

    <div class="small">Plan : <a href="{{ route('strategie.plans.show', $objectif->planStrategique) }}">{{ $objectif->planStrategique->reference }}</a>

    · Orientation : {{ $objectif->orientationStrategique?->code ?? '—' }} · @include('strategie._statut', ['statut' => $objectif->statut])</div>

</div></div>

<div class="card border-0 shadow-sm"><div class="card-header bg-white">Indicateurs ({{ $objectif->indicateurs->count() }})</div>

<ul class="list-group list-group-flush">@forelse($objectif->indicateurs as $ind)

<li class="list-group-item d-flex justify-content-between"><a href="{{ route('strategie.indicateurs.show', $ind) }}"><code>{{ $ind->code }}</code> — {{ $ind->libelle }}</a>

<span class="small">Cible : {{ $ind->valeur_cible ?? '—' }} {{ $ind->unite }}</span></li>

@empty<li class="list-group-item text-muted">Aucun indicateur rattaché.</li>@endforelse</ul></div>

@endsection

