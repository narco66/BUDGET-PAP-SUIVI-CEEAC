@extends('layouts.app')

@section('titre', 'Nouvel indicateur')

@section('contenu')

@include('strategie._nav', ['active' => 'indicateurs'])

<div class="card border-0 shadow-sm"><div class="card-body">

<form method="POST" action="{{ route('strategie.indicateurs.store') }}">@csrf

@include('strategie.indicateurs._form')

</form></div></div>

@endsection

