@extends('layouts.app')

@section('titre', 'Nouveau risque')

@section('contenu')

@include('strategie._nav', ['active' => 'risques'])

<div class="card border-0 shadow-sm"><div class="card-body">

<form method="POST" action="{{ route('strategie.risques.store') }}">@csrf

@include('strategie.risques._form')

</form></div></div>

@endsection

