@extends('layouts.app')
@section('titre', 'Modifier convention '.$convention->code)
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('conventions.update', $convention) }}">@method('PUT')@include('referentiel.conventions._form')</form></div></div>
@endsection
