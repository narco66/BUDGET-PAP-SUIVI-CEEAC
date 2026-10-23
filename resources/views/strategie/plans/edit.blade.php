@extends('layouts.app')
@section('titre', 'Modifier plan')
@section('contenu')
@include('strategie._nav', ['active' => 'plans'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.plans.update', $plan) }}">@csrf @method('PUT') @include('strategie.plans._form')</form></div></div>
@endsection
