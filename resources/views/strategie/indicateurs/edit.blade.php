@extends('layouts.app')

@section('titre', 'Modifier indicateur')

@section('contenu')

@include('strategie._nav', ['active' => 'indicateurs'])

<div class="card border-0 shadow-sm"><div class="card-body">

<form method="POST" action="{{ route('strategie.indicateurs.update', $indicateur) }}">@csrf @method('PUT')

@include('strategie.indicateurs._form')

</form></div></div>

@endsection

