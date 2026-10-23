@extends('layouts.app')
@section('titre', 'Modifier programmation pluri.')
@section('contenu')
@include('strategie._nav', ['active' => 'programmations-pluri'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.programmations-pluri.update', $programmation) }}">@csrf @method('PUT') @include('strategie.programmations-pluri._form')</form></div></div>
@endsection
