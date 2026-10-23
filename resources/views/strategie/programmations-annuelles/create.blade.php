@extends('layouts.app')
@section('titre', 'Nouvelle programmation annuelle')
@section('contenu')
@include('strategie._nav', ['active' => 'programmations-annuelles'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.programmations-annuelles.store') }}">@csrf @include('strategie.programmations-annuelles._form')</form></div></div>
@endsection
