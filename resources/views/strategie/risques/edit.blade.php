@extends('layouts.app')

@section('titre', 'Modifier risque')

@section('contenu')

@include('strategie._nav', ['active' => 'risques'])

<div class="card border-0 shadow-sm"><div class="card-body">

<form method="POST" action="{{ route('strategie.risques.update', $risque) }}">@csrf @method('PUT')

@include('strategie.risques._form')

</form></div></div>

@endsection

