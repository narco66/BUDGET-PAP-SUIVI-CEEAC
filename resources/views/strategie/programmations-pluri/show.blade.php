@extends('layouts.app')
@section('titre', $programmation->reference)
@section('contenu')
@include('strategie._nav', ['active' => 'programmations-pluri'])
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('strategie.programmations-pluri.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
    @can('strategie.create')<a href="{{ route('strategie.programmations-annuelles.create', ['pluri' => $programmation->id]) }}" class="btn btn-sm btn-primary">+ Prog. annuelle</a>@endcan
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h2 class="h5">{{ $programmation->reference }} · {{ $programmation->annee_debut }}-{{ $programmation->annee_fin }}</h2>
    <p class="text-muted mb-0">Plan : {{ $programmation->planStrategique?->reference }} · @include('strategie._statut', ['statut' => $programmation->statut])</p>
</div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white">Déclinaisons annuelles</div>
<ul class="list-group list-group-flush">@forelse($programmation->programmationsAnnuelles as $ann)
<li class="list-group-item d-flex justify-content-between"><a href="{{ route('strategie.programmations-annuelles.show', $ann) }}">Exercice {{ $ann->exercice?->annee }}</a>@include('strategie._statut', ['statut' => $ann->statut])</li>
@empty<li class="list-group-item text-muted">Aucune déclinaison annuelle.</li>@endforelse</ul></div>
@endsection
