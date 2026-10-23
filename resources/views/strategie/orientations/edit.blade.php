@extends('layouts.app')
@section('titre', 'Modifier orientation')
@section('contenu')
@include('strategie._nav', ['active' => 'orientations'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.orientations.update', $orientation) }}">@csrf @method('PUT') @include('strategie.orientations._form')</form></div></div>
@endsection
