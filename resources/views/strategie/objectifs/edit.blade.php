@extends('layouts.app')

@section('titre', 'Modifier objectif')

@section('contenu')

@include('strategie._nav', ['active' => 'objectifs'])

<div class="card border-0 shadow-sm"><div class="card-body">

<h1 class="h5 mb-3">Modifier {{ $objectif->code }}</h1>

<form method="POST" action="{{ route('strategie.objectifs.update', $objectif) }}">@csrf @method('PUT')

@include('strategie.objectifs._form')

</form></div></div>

@endsection

