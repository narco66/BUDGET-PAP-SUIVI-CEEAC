@extends('layouts.app')

@section('titre', $risque->code)

@section('contenu')

@include('strategie._nav', ['active' => 'risques'])

<div class="d-flex justify-content-between mb-3">

    <a href="{{ route('strategie.risques.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>

    @can('strategie.update')<a href="{{ route('strategie.risques.edit', $risque) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan

</div>

<div class="card border-0 shadow-sm"><div class="card-body">

    <h2 class="h5"><code>{{ $risque->code }}</code></h2>

    <p>{{ $risque->description }}</p>

    <div class="row small g-2">

        <div class="col-md-4">Plan : <a href="{{ route('strategie.plans.show', $risque->planStrategique) }}">{{ $risque->planStrategique->reference }}</a></div>

        <div class="col-md-4">Projet : {{ $risque->projet?->code ?? '—' }}</div>

        <div class="col-md-4">Responsable : {{ $risque->responsable?->name ?? '—' }}</div>

        <div class="col-md-4">Probabilité : {{ ucfirst($risque->probabilite) }}</div>

        <div class="col-md-4">Impact : {{ ucfirst($risque->impact) }}</div>

        <div class="col-md-4">@include('strategie._statut', ['statut' => $risque->statut])</div>

    </div>

    @if($risque->mitigation)<hr><p class="small mb-0"><strong>Mitigation :</strong> {{ $risque->mitigation }}</p>@endif

</div></div>

@endsection

