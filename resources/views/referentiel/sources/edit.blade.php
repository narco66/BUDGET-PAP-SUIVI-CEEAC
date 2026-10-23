@extends('layouts.app')
@section('titre', 'Modifier '.$source->code)
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('sources.update', $source) }}">@method('PUT')@include('referentiel.sources._form')</form></div></div>
@endsection
