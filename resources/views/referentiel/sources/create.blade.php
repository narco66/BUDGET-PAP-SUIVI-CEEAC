@extends('layouts.app')
@section('titre', 'Nouvelle source de financement')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('sources.store') }}">@include('referentiel.sources._form')</form></div></div>
@endsection
