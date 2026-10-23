@extends('layouts.app')
@section('titre', 'Modifier programme')
@section('contenu')
@include('strategie._nav', ['active' => 'programmes'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.programmes.update', $programme) }}">@csrf @method('PUT') @include('strategie.programmes._form')</form></div></div>
@endsection
