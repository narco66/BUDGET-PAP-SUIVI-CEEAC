@extends('layouts.app')

@section('titre', $indicateur->code)

@section('contenu')

@include('strategie._nav', ['active' => 'indicateurs'])

<div class="d-flex justify-content-between mb-3">

    <a href="{{ route('strategie.indicateurs.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>

    @can('strategie.update')<a href="{{ route('strategie.indicateurs.edit', $indicateur) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan

</div>

<div class="card border-0 shadow-sm"><div class="card-body">

    <h2 class="h5">{{ $indicateur->libelle }}</h2>

    <div class="row small g-2 mt-2">

        <div class="col-md-4">Objectif : <a href="{{ route('strategie.objectifs.show', $indicateur->objectifStrategique) }}">{{ $indicateur->objectifStrategique->code }}</a></div>

        <div class="col-md-4">Plan : {{ $indicateur->objectifStrategique->planStrategique->reference }}</div>

        <div class="col-md-4">@include('strategie._statut', ['statut' => $indicateur->statut])</div>

        <div class="col-md-3">Référence : {{ $indicateur->valeur_reference ?? '—' }} {{ $indicateur->unite }}</div>

        <div class="col-md-3">Cible : {{ $indicateur->valeur_cible ?? '—' }} {{ $indicateur->unite }}</div>

        <div class="col-md-3">Seuil alerte : {{ $indicateur->seuil_alerte ?? '—' }}</div>

        <div class="col-md-3">Pont RBM : {{ $indicateur->indicateurRbm?->code ?? '—' }}</div>

    </div>

</div></div>

@endsection

