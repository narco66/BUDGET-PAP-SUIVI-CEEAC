@extends('layouts.app')
@section('titre', 'Nouveau plan stratégique')
@section('contenu')
@include('strategie._nav', ['active' => 'plans'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.plans.store') }}">@csrf @include('strategie.plans._form')</form></div></div>
@endsection
