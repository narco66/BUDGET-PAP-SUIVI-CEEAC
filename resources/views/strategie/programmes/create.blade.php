@extends('layouts.app')
@section('titre', 'Nouveau programme stratégique')
@section('contenu')
@include('strategie._nav', ['active' => 'programmes'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.programmes.store') }}">@csrf @include('strategie.programmes._form')</form></div></div>
@endsection
