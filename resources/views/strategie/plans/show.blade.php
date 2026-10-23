@extends('layouts.app')

@section('titre', $plan->reference)

@section('contenu')

@include('strategie._nav', ['active' => 'plans'])

<div class="d-flex justify-content-between mb-3">

    <a href="{{ route('strategie.plans.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>

    <div>@can('strategie.create')

    <a href="{{ route('strategie.orientations.create', ['plan' => $plan->id]) }}" class="btn btn-sm btn-outline-primary me-1">+ Orientation</a>

    <a href="{{ route('strategie.objectifs.create', ['plan' => $plan->id]) }}" class="btn btn-sm btn-outline-primary me-1">+ Objectif</a>

    <a href="{{ route('strategie.risques.create', ['plan' => $plan->id]) }}" class="btn btn-sm btn-outline-primary me-1">+ Risque</a>

    @endcan

    @can('strategie.update')<a href="{{ route('strategie.plans.edit', $plan) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan</div>

</div>

@include('strategie._validation_plan', ['plan' => $plan])

<div class="card border-0 shadow-sm mb-3"><div class="card-body">

    <h2 class="h5">{{ $plan->intitule }}</h2>

    <p class="text-muted">{{ $plan->description }}</p>

    <div class="small">Vision : {{ $plan->vision?->reference ?? '—' }} · @include('strategie._statut', ['statut' => $plan->statut]) · v{{ $plan->version }}</div>

</div></div>

<div class="row g-3 mb-3">

    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white">Orientations ({{ $plan->orientations->count() }})</div>

    <ul class="list-group list-group-flush">@forelse($plan->orientations as $o)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('strategie.orientations.show', $o) }}">{{ $o->code }} — {{ $o->intitule }}</a>@include('strategie._statut', ['statut' => $o->statut])</li>@empty<li class="list-group-item text-muted">Aucune orientation.</li>@endforelse</ul></div></div>

    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white">Objectifs ({{ $plan->objectifs->count() }})</div>

    <ul class="list-group list-group-flush">@forelse($plan->objectifs as $o)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('strategie.objectifs.show', $o) }}">{{ $o->code }} — {{ $o->intitule }}</a><span class="small text-muted">{{ $o->indicateurs->count() }} indic.</span></li>@empty<li class="list-group-item text-muted">Aucun objectif.</li>@endforelse</ul></div></div>

</div>

<div class="row g-3">

    <div class="col-md-6">@include('strategie._documents', ['attachable' => $plan, 'type' => 'plan'])</div>

    <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Risques ({{ $plan->risques->count() }})</div>

    <ul class="list-group list-group-flush">@forelse($plan->risques as $r)<li class="list-group-item"><a href="{{ route('strategie.risques.show', $r) }}">{{ $r->code }}</a> — {{ Str::limit($r->description, 60) }}</li>@empty<li class="list-group-item text-muted">Aucun risque.</li>@endforelse</ul></div></div>

</div>

@endsection

