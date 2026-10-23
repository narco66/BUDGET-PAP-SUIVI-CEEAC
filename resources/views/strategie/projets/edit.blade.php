@extends('layouts.app')
@section('titre', 'Modifier projet')
@section('contenu')
@include('strategie._nav', ['active' => 'projets'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.projets.update', $projet) }}">@csrf @method('PUT') @include('strategie.projets._form')</form></div></div>
@endsection
