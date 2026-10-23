@extends('layouts.app')
@section('titre', 'Nouvelle convention — '.$partenaire->libelle)
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('conventions.store') }}">@include('referentiel.conventions._form')</form></div></div>
@endsection
