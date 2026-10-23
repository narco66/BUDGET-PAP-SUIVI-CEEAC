@extends('layouts.app')
@section('titre', 'Nouvelle programmation pluriannuelle')
@section('contenu')
@include('strategie._nav', ['active' => 'programmations-pluri'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.programmations-pluri.store') }}">@csrf @include('strategie.programmations-pluri._form')</form></div></div>
@endsection
