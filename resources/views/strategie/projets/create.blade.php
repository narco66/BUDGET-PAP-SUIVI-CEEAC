@extends('layouts.app')
@section('titre', 'Nouveau projet')
@section('contenu')
@include('strategie._nav', ['active' => 'projets'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.projets.store') }}">@csrf @include('strategie.projets._form')</form></div></div>
@endsection
