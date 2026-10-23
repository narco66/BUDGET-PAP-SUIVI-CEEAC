@extends('layouts.app')

@section('titre', 'Programmation annuelle')

@section('contenu')

@include('strategie._nav', ['active' => 'programmations-annuelles'])

<a href="{{ route('strategie.programmations-annuelles.index') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Retour</a>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">

    <h2 class="h5">Exercice {{ $programmation->exercice?->annee }}</h2>

    <p class="text-muted">Pluriannuelle : {{ $programmation->programmationPluriannuelle?->reference }} · @include('strategie._statut', ['statut' => $programmation->statut])</p>

    @if($programmation->pap)

    <p class="small mb-0">PAP rattaché : <a href="{{ route('paps.show', $programmation->pap) }}">{{ $programmation->pap->libelle }}</a></p>

    @else<p class="small text-warning mb-0">Aucun PAP rattaché — <a href="{{ route('strategie.pont-pap', ['exercice' => $programmation->exercice_id]) }}">Lier via le pont PAP</a></p>@endif

    <hr><h3 class="h6">Activités prévues</h3><p>{{ $programmation->activites_prevues ?: '—' }}</p>

    <h3 class="h6">Ressources prévues</h3><p>{{ $programmation->ressources_prevues ?: '—' }}</p>

    <h3 class="h6">Indicateurs prévus</h3><p>{{ $programmation->indicateurs_prevues ?: '—' }}</p>

    @can('strategie.update')<a href="{{ route('strategie.programmations-annuelles.edit', $programmation) }}" class="btn btn-sm btn-primary mt-2">Modifier</a>@endcan

</div></div>

@if($reconciliation['execution_pap'] ?? null)

<div class="card border-0 shadow-sm"><div class="card-header bg-white">Réconciliation PAP exercice {{ $programmation->exercice?->annee }}</div>

<div class="card-body small row g-2">

    <div class="col-md-3">Activités PAP : <strong>{{ $reconciliation['execution_pap']['activites'] ?? 0 }}</strong></div>

    <div class="col-md-3">Taux financier : <strong>{{ $reconciliation['execution_pap']['taux_financier'] ?? 0 }}%</strong></div>

    <div class="col-md-3">Piliers orientés : <strong>{{ $reconciliation['piliers_orientes_pct'] }}%</strong></div>

    <div class="col-md-3">Activités → projet : <strong>{{ $reconciliation['activites_projet_pct'] }}%</strong></div>

</div></div>

@endif

@endsection

